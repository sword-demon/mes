<?php

namespace App\Console\Commands;

use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Equipment\Service\EquipmentMessageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Contracts\Repository;
use RuntimeException;
use Throwable;

#[Signature('mes:consume-mqtt {--max-messages=0 : 处理指定数量的有效消息后退出，0 为常驻}')]
#[Description('订阅设备 MQTT 消息，保存历史并更新设备状态')]
class ConsumeMqtt extends Command
{
    public function handle(MqttClient $mqtt, Repository $subscriptions, EquipmentMessageService $messages): int
    {
        $limit = filter_var($this->option('max-messages'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($limit === false) {
            $this->error('--max-messages 必须为非负整数');

            return self::INVALID;
        }

        $factory = config('mqtt.factory');

        if (! is_string($factory) || $factory === '' || strpbrk($factory, '/+#') !== false) {
            $this->error('MQTT_FACTORY 必须为不含 /、+、# 的工厂标识');

            return self::INVALID;
        }

        $stopping = false;
        $this->trap([SIGINT, SIGTERM], function () use ($mqtt, &$stopping): void {
            $stopping = true;
            $mqtt->interrupt();
        });
        $received = 0;
        $failure = null;
        $exitCode = self::SUCCESS;

        try {
            $settings = (new ConnectionSettings)
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'))
                ->setConnectTimeout(5)
                ->setKeepAliveInterval(30);
            $mqtt->connect($settings, true);

            foreach (EquipmentData::MESSAGE_TYPES as $kind) {
                $mqtt->subscribe("mes/{$factory}/+/{$kind}", function (string $topic, string $message) use ($mqtt, $messages, $limit, &$received, &$failure): void {
                    if ($failure !== null || ($limit > 0 && $received >= $limit)) {
                        return;
                    }

                    try {
                        $messages->consume($topic, $message);
                        $received++;
                    } catch (ValidationException) {
                        $this->warn("忽略无效设备消息：{$topic}");

                        return;
                    } catch (Throwable $exception) {
                        // SDK 会捕获回调异常，需在循环外重新抛出，避免静默丢失入库失败。
                        $failure = $exception;
                        $mqtt->interrupt();

                        return;
                    }

                    if ($limit > 0 && $received >= $limit) {
                        $mqtt->interrupt();
                    }
                }, 1);
            }

            $startedAt = microtime(true);
            $deadline = now()->addSeconds(5);

            while ($subscriptions->countSubscriptions() !== count(EquipmentData::MESSAGE_TYPES)) {
                if ($stopping) {
                    break;
                }

                if ($failure !== null) {
                    throw $failure;
                }

                if ($subscriptions->countPendingOutgoingMessages() === 0) {
                    throw new RuntimeException('MQTT 主题订阅被拒绝');
                }

                if (now()->greaterThanOrEqualTo($deadline)) {
                    throw new RuntimeException('MQTT 主题订阅确认超时');
                }

                $mqtt->loopOnce($startedAt, true);
            }

            if (! $stopping) {
                $topics = implode(',', EquipmentData::MESSAGE_TYPES);
                $this->info("已订阅 mes/{$factory}/+/{$topics}");
                $mqtt->loop();
            }

            if ($failure !== null) {
                throw $failure;
            }

            $this->info("已处理 {$received} 条设备消息");
        } catch (Throwable $exception) {
            report($exception);
            $this->error('MQTT 消费失败，请检查应用日志');
            $exitCode = self::FAILURE;
        } finally {
            if ($mqtt->isConnected()) {
                try {
                    $mqtt->disconnect();
                } catch (Throwable $exception) {
                    report($exception);
                    $this->error('MQTT 断开连接失败，请检查应用日志');
                    $exitCode = self::FAILURE;
                }
            }
        }

        return $exitCode;
    }
}
