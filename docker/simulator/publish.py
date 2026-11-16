"""Publish simulated MES device telemetry to EMQX.

Topics follow `mes/{factory}/{device}/status|data|alarm`. Devices cycle through
running / standby / fault / maintenance / offline. Output only grows while a
device is running, so maintenance and offline devices never inflate production.
"""

import json
import os
import random
import signal
import time
from datetime import datetime, timezone

import paho.mqtt.client as mqtt

HOST = os.getenv("MQTT_HOST", "emqx")
PORT = int(os.getenv("MQTT_PORT", "1883"))
FACTORY = os.getenv("MQTT_FACTORY", "F001")
DEVICES = [d.strip() for d in os.getenv("MQTT_DEVICES", "").split(",") if d.strip()]
INTERVAL = float(os.getenv("MQTT_INTERVAL", "5"))
USERNAME = os.getenv("MQTT_USERNAME", "")
PASSWORD = os.getenv("MQTT_PASSWORD", "")

RUNNING = "running"
STANDBY = "standby"
FAULT = "fault"
MAINTENANCE = "maintenance"
OFFLINE = "offline"

PRODUCTIVE_STATES = {RUNNING}

# status -> [(next status, weight)]
TRANSITIONS = {
    RUNNING: [(RUNNING, 90), (STANDBY, 6), (FAULT, 2), (OFFLINE, 2)],
    STANDBY: [(RUNNING, 60), (STANDBY, 30), (MAINTENANCE, 10)],
    FAULT: [(MAINTENANCE, 70), (FAULT, 30)],
    MAINTENANCE: [(STANDBY, 70), (MAINTENANCE, 30)],
    OFFLINE: [(STANDBY, 80), (OFFLINE, 20)],
}

ALARM_MESSAGES = {
    "over_temperature": "温度超限",
    "over_speed": "转速超限",
    "over_pressure": "压力超限",
    "device_fault": "设备故障",
}

# 量程与分级告警阈值集中在一处，与 Laravel 侧保持同一口径。
TEMPERATURE_RANGE = (35.0, 70.0)
SPEED_RANGE = (800, 1500)
PRESSURE_RANGE = (0.4, 0.9)

THRESHOLDS = {
    "over_temperature": {"info": 60.0, "warning": 64.0, "critical": 68.0},
    "over_speed": {"info": 1200, "warning": 1350, "critical": 1450},
    "over_pressure": {"info": 0.70, "warning": 0.80, "critical": 0.85},
}

ALARM_METRICS = {
    "over_temperature": "temperature",
    "over_speed": "speed",
    "over_pressure": "pressure",
}

stop = False


def handle_stop(signum, frame):
    global stop
    stop = True


def now():
    return datetime.now(timezone.utc).isoformat()


def pick_state(current):
    choices = TRANSITIONS[current]
    return random.choices(
        [state for state, _ in choices],
        weights=[weight for _, weight in choices],
    )[0]


def topic(device, kind):
    return f"mes/{FACTORY}/{device}/{kind}"


def publish(client, destination, payload):
    message = client.publish(destination, payload, qos=1)

    if message.rc != mqtt.MQTT_ERR_SUCCESS:
        raise RuntimeError(f"MQTT publish failed: {message.rc}")

    message.wait_for_publish(timeout=5)

    if not message.is_published():
        raise RuntimeError("MQTT acknowledgement timed out")


def main():
    if not DEVICES:
        raise SystemExit("MQTT_DEVICES is empty")

    signal.signal(signal.SIGTERM, handle_stop)
    signal.signal(signal.SIGINT, handle_stop)

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="mes-simulator")

    if USERNAME:
        client.username_pw_set(USERNAME, PASSWORD or None)

    connected = False

    def on_connect(client, userdata, flags, reason_code, properties):
        nonlocal connected

        if reason_code.is_failure:
            raise RuntimeError("MQTT connection rejected")

        connected = True

    client.on_connect = on_connect
    client.connect(HOST, PORT, keepalive=30)
    deadline = time.monotonic() + 5

    while not connected:
        if stop:
            client.disconnect()
            return

        if time.monotonic() >= deadline:
            raise RuntimeError("MQTT connection acknowledgement timed out")

        if client.loop(timeout=1) != mqtt.MQTT_ERR_SUCCESS:
            raise RuntimeError("MQTT connection failed")

    client.loop_start()

    try:
        simulate(client)
    finally:
        client.disconnect()
        client.loop_stop()


def simulate(client):
    states = {device: RUNNING for device in DEVICES}
    output = {device: 0 for device in DEVICES}
    runtime = {device: 0.0 for device in DEVICES}
    sampled_at = {device: time.monotonic() for device in DEVICES}

    # 先发一次初始状态，消费者才有基线，不必等第一次状态跃迁。
    for device in DEVICES:
        initial = {
            "device_code": device,
            "timestamp": now(),
            "status": states[device],
            "temperature": 35.0,
            "speed": 800,
            "pressure": 0.4,
            "output": output[device],
            "runtime_seconds": 0,
        }
        publish(
            client,
            topic(device, "status"),
            json.dumps(initial, ensure_ascii=False),
        )

    while not stop:
        for device in DEVICES:
            previous = states[device]
            sampled_now = time.monotonic()

            if previous == RUNNING:
                runtime[device] += sampled_now - sampled_at[device]

            sampled_at[device] = sampled_now
            current = pick_state(previous)
            states[device] = current

            timestamp = now()

            running = current in PRODUCTIVE_STATES
            temperature = round(random.uniform(*TEMPERATURE_RANGE), 1) if running else round(random.uniform(20.0, 40.0), 1)
            speed = random.randint(*SPEED_RANGE) if running else 0
            pressure = round(random.uniform(*PRESSURE_RANGE), 2) if running else 0.0

            if running:
                output[device] += random.randint(1, 5)

            payload = {
                "device_code": device,
                "timestamp": timestamp,
                "status": current,
                "temperature": temperature,
                "speed": speed,
                "pressure": pressure,
                "output": output[device],
                "runtime_seconds": int(runtime[device]),
            }

            if current != previous:
                publish(
                    client,
                    topic(device, "status"),
                    json.dumps(payload, ensure_ascii=False),
                )

            publish(
                client,
                topic(device, "data"),
                json.dumps(payload, ensure_ascii=False),
            )

            alarm = None
            level = None

            for alarm_code, metric in ALARM_METRICS.items():
                measured = payload[metric]
                level = next(
                    (
                        candidate
                        for candidate in ("critical", "warning", "info")
                        if measured > THRESHOLDS[alarm_code][candidate]
                    ),
                    None,
                )

                if level is not None:
                    alarm = alarm_code
                    break

            if alarm is None and current == FAULT:
                alarm = "device_fault"
                level = "critical"

            if alarm:
                publish(
                    client,
                    topic(device, "alarm"),
                    json.dumps(
                        {
                            **payload,
                            "alarm_code": alarm,
                            "message": ALARM_MESSAGES[alarm],
                            "level": level,
                        },
                        ensure_ascii=False,
                    ),
                )

        time.sleep(INTERVAL)


if __name__ == "__main__":
    main()
