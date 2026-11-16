import importlib.util
import json
import unittest
from datetime import datetime, timezone
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, patch


spec = importlib.util.spec_from_file_location("simulator", Path(__file__).with_name("publish.py"))
simulator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(simulator)


class SimulatorTest(unittest.TestCase):
    def run_simulator(self, client, cycles=1):
        simulator.stop = False

        def stop_after_cycle(_):
            nonlocal cycles
            cycles -= 1
            simulator.stop = cycles == 0

        with patch.object(simulator.mqtt, "Client", return_value=client), \
             patch.object(simulator, "DEVICES", ["TEST-001"]), \
             patch.object(simulator.time, "sleep", side_effect=stop_after_cycle):
            simulator.main()

    def client(self, rejected=False):
        client = Mock()
        def connect(**kwargs):
            client.on_connect(client, None, None, SimpleNamespace(is_failure=rejected), None)
            return simulator.mqtt.MQTT_ERR_SUCCESS

        client.loop.side_effect = connect
        message = Mock(rc=simulator.mqtt.MQTT_ERR_SUCCESS)
        message.is_published.return_value = True
        client.publish.return_value = message

        return client

    def test_rejected_connection_does_not_publish(self):
        client = self.client(rejected=True)

        with self.assertRaisesRegex(RuntimeError, "connection rejected"):
            self.run_simulator(client)

        client.publish.assert_not_called()

    def test_publish_failure_stops_simulation(self):
        client = self.client()
        client.publish.return_value.rc = simulator.mqtt.MQTT_ERR_NO_CONN

        with self.assertRaisesRegex(RuntimeError, "publish failed"):
            self.run_simulator(client)

        self.assertEqual(client.publish.call_count, 1)
        client.disconnect.assert_called_once()
        client.loop_stop.assert_called_once()

    def test_missing_connection_acknowledgement_times_out(self):
        client = self.client()
        client.loop.side_effect = None
        client.loop.return_value = simulator.mqtt.MQTT_ERR_SUCCESS

        with patch.object(simulator.time, "monotonic", side_effect=[0, 6]):
            with self.assertRaisesRegex(RuntimeError, "connection acknowledgement timed out"):
                self.run_simulator(client)

        client.publish.assert_not_called()

    def test_connection_error_does_not_publish(self):
        client = self.client()
        client.loop.side_effect = None
        client.loop.return_value = simulator.mqtt.MQTT_ERR_CONN_LOST

        with self.assertRaisesRegex(RuntimeError, "connection failed"):
            self.run_simulator(client)

        client.publish.assert_not_called()

    def test_unacknowledged_publish_stops_simulation(self):
        client = self.client()
        client.publish.return_value.is_published.return_value = False

        with self.assertRaisesRegex(RuntimeError, "acknowledgement timed out"):
            self.run_simulator(client)

    def test_successful_cycle_waits_for_broker_acknowledgement(self):
        client = self.client()

        self.run_simulator(client)

        self.assertGreaterEqual(client.publish.call_count, 2)
        client.publish.return_value.wait_for_publish.assert_called_with(timeout=5)
        self.assertTrue(all(call.kwargs.get("qos") == 1 for call in client.publish.call_args_list))
        client.disconnect.assert_called_once()

    def test_all_topics_publish_a_complete_snapshot_with_matching_cycle_values(self):
        client = self.client()

        with patch.object(simulator, "FACTORY", "TEST-FACTORY"), \
             patch.object(simulator.random, "choices", return_value=["fault"]), \
             patch.object(simulator.random, "uniform", return_value=30.0), \
             patch.object(simulator, "datetime") as clock:
            clock.now.return_value = datetime(2026, 11, 16, 4, 0, tzinfo=timezone.utc)
            self.run_simulator(client)

        messages = [
            (call.args[0], json.loads(call.args[1]))
            for call in client.publish.call_args_list
        ]
        self.assertEqual([destination for destination, _ in messages], [
            "mes/TEST-FACTORY/TEST-001/status",
            "mes/TEST-FACTORY/TEST-001/status",
            "mes/TEST-FACTORY/TEST-001/data",
            "mes/TEST-FACTORY/TEST-001/alarm",
        ])
        self.assertEqual(messages[0][1], {
            "device_code": "TEST-001",
            "timestamp": "2026-11-16T04:00:00+00:00",
            "status": "running",
            "temperature": 35.0,
            "speed": 800,
            "pressure": 0.4,
            "output": 0,
        })
        expected = {
            "device_code": "TEST-001",
            "timestamp": "2026-11-16T04:00:00+00:00",
            "status": "fault",
            "temperature": 30.0,
            "speed": 0,
            "pressure": 0.0,
            "output": 0,
        }
        self.assertEqual(messages[1][1], expected)
        self.assertEqual(messages[2][1], expected)
        self.assertEqual(messages[3][1], {
            **expected,
            "alarm_code": "device_fault",
            "message": "设备故障",
            "level": "critical",
        })

    def test_maintenance_and_offline_preserve_output_accumulated_while_running(self):
        client = self.client()

        with patch.object(simulator.random, "choices", side_effect=[
            ["running"], ["fault"], ["maintenance"], ["maintenance"],
            ["standby"], ["running"], ["offline"], ["offline"],
        ]), patch.object(simulator.random, "randint", side_effect=[1000, 3, 1100, 2]), \
             patch.object(simulator.random, "uniform", side_effect=[
                 50.0, 0.6, 30.0, 28.0, 27.0, 26.0, 55.0, 0.7, 25.0, 24.0,
             ]):
            self.run_simulator(client, cycles=8)

        data = [
            json.loads(call.args[1])
            for call in client.publish.call_args_list
            if call.args[0].endswith("/data")
        ]
        self.assertEqual([payload["status"] for payload in data], [
            "running", "fault", "maintenance", "maintenance",
            "standby", "running", "offline", "offline",
        ])
        self.assertEqual([payload["output"] for payload in data], [3, 3, 3, 3, 3, 5, 5, 5])
        self.assertEqual([payload["speed"] for payload in data], [1000, 0, 0, 0, 0, 1100, 0, 0])


if __name__ == "__main__":
    unittest.main()
