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

# 量程与告警阈值集中在一处，避免生成区间和判定条件各自漂移。
TEMPERATURE_RANGE = (35.0, 70.0)
SPEED_RANGE = (800, 1500)
PRESSURE_RANGE = (0.4, 0.9)

THRESHOLDS = {
    "over_temperature": 68.0,
    "over_speed": 1450,
    "over_pressure": 0.85,
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


def main():
    if not DEVICES:
        raise SystemExit("MQTT_DEVICES is empty")

    signal.signal(signal.SIGTERM, handle_stop)
    signal.signal(signal.SIGINT, handle_stop)

    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id="mes-simulator")

    if USERNAME:
        client.username_pw_set(USERNAME, PASSWORD or None)

    while not stop:
        try:
            client.connect(HOST, PORT, keepalive=30)
            break
        except OSError:
            time.sleep(2)

    client.loop_start()

    states = {device: RUNNING for device in DEVICES}
    output = {device: 0 for device in DEVICES}

    # 先发一次初始状态，消费者才有基线，不必等第一次状态跃迁。
    for device in DEVICES:
        client.publish(
            topic(device, "status"),
            json.dumps(
                {
                    "device_code": device,
                    "timestamp": now(),
                    "status": states[device],
                },
                ensure_ascii=False,
            ),
        )

    while not stop:
        for device in DEVICES:
            previous = states[device]
            current = pick_state(previous)
            states[device] = current

            timestamp = now()

            if current != previous:
                client.publish(
                    topic(device, "status"),
                    json.dumps(
                        {"device_code": device, "timestamp": timestamp, "status": current},
                        ensure_ascii=False,
                    ),
                )

            running = current in PRODUCTIVE_STATES
            temperature = round(random.uniform(*TEMPERATURE_RANGE), 1) if running else round(random.uniform(20.0, 40.0), 1)
            speed = random.randint(*SPEED_RANGE) if running else 0
            pressure = round(random.uniform(*PRESSURE_RANGE), 2) if running else 0.0

            if running:
                output[device] += random.randint(1, 5)

            client.publish(
                topic(device, "data"),
                json.dumps(
                    {
                        "device_code": device,
                        "timestamp": timestamp,
                        "status": current,
                        "temperature": temperature,
                        "speed": speed,
                        "pressure": pressure,
                        "output": output[device],
                    },
                    ensure_ascii=False,
                ),
            )

            alarm = None

            if temperature > THRESHOLDS["over_temperature"]:
                alarm = "over_temperature"
            elif speed > THRESHOLDS["over_speed"]:
                alarm = "over_speed"
            elif pressure > THRESHOLDS["over_pressure"]:
                alarm = "over_pressure"
            elif current == FAULT:
                alarm = "device_fault"

            if alarm:
                client.publish(
                    topic(device, "alarm"),
                    json.dumps(
                        {
                            "device_code": device,
                            "timestamp": timestamp,
                            "alarm_code": alarm,
                            "message": ALARM_MESSAGES[alarm],
                            "level": "critical" if current == FAULT else "warning",
                        },
                        ensure_ascii=False,
                    ),
                )

        time.sleep(INTERVAL)

    client.loop_stop()
    client.disconnect()


if __name__ == "__main__":
    main()
