<?php

namespace App\Providers;

use App\Core\Audit\AuditContext;
use App\Modules\System\Model\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Contracts\Repository as MqttRepository;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

/**
 * 应用服务提供者。
 *
 * 负责绑定 MQTT 客户端单例、注册 Eloquent 多态别名、配置 API 限流规则，
 * 以及统一设置 Carbon / 数据库 / 密码规则等全局默认行为。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册应用服务到容器。
     *
     * 此处完成 MQTT 相关依赖的绑定：仓储注册为单例（进程内持久化订阅状态），
     * 客户端以接口注入方式创建，连接参数从 config/mqtt.php 读取。
     */
    public function register(): void
    {
        // 审计上下文：请求/进程级单例，供 HTTP、队列任务与系统进程共享来源信息
        $this->app->singleton(AuditContext::class);

        // MQTT 订阅状态仓储：进程级单例，避免多次连接期间重复订阅
        $this->app->singleton(MqttRepository::class, MemoryRepository::class);

        // MQTT 客户端绑定：闭包内延迟实例化，注入已注册的仓储
        $this->app->bind(MqttClientContract::class, fn ($app) => new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id'),
            MqttClient::MQTT_3_1_1,
            $app->make(MqttRepository::class),
        ));
    }

    /**
     * 启动应用服务。
     *
     * 在服务全部注册完成后执行，顺序注册多态映射 → 全局默认 → 限流规则。
     */
    public function boot(): void
    {
        // 多态别名映射：把 morphMany / morphTo 用到的 User 统一到当前模型，
        // 同时兼容遗留的 App\Models\User 字面值（历史数据回填用）
        Relation::morphMap([
            User::class => User::class,
            'App\\Models\\User' => User::class,
        ]);

        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * 配置 API 限流规则。
     *
     * 两个独立的 limiter：`api` 控制通用接口每分钟 60 次，按 IP 计数；
     * `login` 专门针对登录接口，每分钟仅 5 次，按「邮箱 + IP」组合限流，
     * 防止撞库同时不封掉共用出口 IP 的正常用户。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip())));
    }

    /**
     * 配置生产环境默认行为。
     *
     * 三项全局策略：
     *   1. Date::use(CarbonImmutable::class) — 全程使用不可变 Carbon，
     *      避免 DatePeriod / DateTime::modify 等链式调用误改原对象；
     *   2. DB::prohibitDestructiveCommands(true) — 生产环境禁止 migrate:fresh / migrate:refresh，
     *      防止误操作删库；
     *   3. Password::defaults(...) — 生产环境强制密码复杂度（12 位 + 大小写 + 数字 + 符号 + 不泄露），
     *      本地环境返回 null 即无默认约束，开发更轻量。
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
