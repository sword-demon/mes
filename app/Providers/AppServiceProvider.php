<?php

namespace App\Providers;

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

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MqttRepository::class, MemoryRepository::class);
        $this->app->bind(MqttClientContract::class, fn ($app) => new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id'),
            MqttClient::MQTT_3_1_1,
            $app->make(MqttRepository::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            User::class => User::class,
            'App\\Models\\User' => User::class,
        ]);

        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters used by the API.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            // Bucketing per user keeps one busy browser from exhausting the
            // budget of every other client sharing the same address. The IP
            // fallback only applies if this limiter is reused on a public route.
            return Limit::perMinute((int) config('app.api_rate_limit'))
                ->by($user === null ? 'ip:'.$request->ip() : 'user:'.$user->getAuthIdentifier());
        });

        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute((int) config('app.login_ip_rate_limit'))
            ->by('ip:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip())));
    }

    /**
     * Configure default behaviors for production-ready applications.
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
