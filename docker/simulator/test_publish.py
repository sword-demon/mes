import importlib.util
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, patch


spec = importlib.util.spec_from_file_location("simulator", Path(__file__).with_name("publish.py"))
simulator = importlib.util.module_from_spec(spec)
spec.loader.exec_module(simulator)


class SimulatorTest(unittest.TestCase):
    def run_simulator(self, client):
        simulator.stop = False

        def stop_after_cycle(_):
            simulator.stop = True

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


if __name__ == "__main__":
    unittest.main()
