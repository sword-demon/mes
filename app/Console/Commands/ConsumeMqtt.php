<?php

namespace App\Console\Commands;

use App\Core\Audit\AuditContext;
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
    public function handle(
        MqttClient $mqtt,
        Repository $subscriptions,
        EquipmentMessageService $messages,
        AuditContext $context,
    ): int {
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

        // 设备消息消费属于系统行为：由它触发的告警日志记 source=system。
        $context->set(AuditContext::SOURCE_SYSTEM);

        $stopping = false;
        $this->trap([SIGINT, SIGTERM], function () use ($mqtt, &$stopping): void {
            $stopping = true;
            $mqtt->interrupt();
        });
        $received = 0;
        $invalid = 0;
        $invalidPending = 0;
        $lastInvalidWarningAt = 0.0;
        $processedSinceReport = 0;
        $lastReportAt = microtime(true);
        $failure = null;
        $exitCode = self::SUCCESS;

        try {
            $settings = (new ConnectionSettings)
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'))
                ->setConnectTimeout(5)
                ->setKeepAliveInterval(30);
            // php-mqtt sends the QoS 1 PUBACK before invoking the callback. A persistent
            // session covers messages missed while offline, not a failure inside the callback.
            $mqtt->connect($settings, (bool) config('mqtt.clean_session', false));

            foreach (EquipmentData::MESSAGE_TYPES as $kind) {
                $mqtt->subscribe("mes/{$factory}/+/{$kind}", function (string $topic, string $message) use ($mqtt, $messages, $limit, &$received, &$invalid, &$invalidPending, &$lastInvalidWarningAt, &$processedSinceReport, &$lastReportAt, &$failure): void {
                    if ($failure !== null || ($limit > 0 && $received >= $limit)) {
                        return;
                    }

                    try {
                        $messages->consume($topic, $message);
                        $received++;
                        $processedSinceReport++;
                    } catch (ValidationException) {
                        $invalid++;
                        $invalidPending++;

                        if (microtime(true) - $lastInvalidWarningAt >= 5) {
                            $this->warn("忽略无效设备消息：{$topic}（最近 {$invalidPending} 条）");
                            $invalidPending = 0;
                            $lastInvalidWarningAt = microtime(true);
                        }

                        return;
                    } catch (Throwable $exception) {
                        // SDK 会捕获回调异常；记录失败并在循环外退出，避免继续消费后续消息。
                        $failure = $exception;
                        $mqtt->interrupt();

                        return;
                    }

                    if (microtime(true) - $lastReportAt >= 30) {
                        $this->info(sprintf(
                            'MQTT 消费中：已处理 %d 条，无效 %d 条，近 30 秒 %d 条，内存 %.1f MB',
                            $received,
                            $invalid,
                            $processedSinceReport,
                            memory_get_usage(true) / 1048576,
                        ));
                        $processedSinceReport = 0;
                        $lastReportAt = microtime(true);
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

            $this->info("已处理 {$received} 条设备消息，忽略 {$invalid} 条无效消息");
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
