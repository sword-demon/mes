<?php

use App\Http\Responses\ApiResponse;
use App\Modules\System\Model\User;
use App\Modules\System\Resource\UserResource;

test('successful responses use the api envelope', function () {
    $token = User::factory()->create()->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('code', 200)
        ->assertJsonPath('message', 'success')
        ->assertJsonPath('data.name', fn ($name) => is_string($name))
        ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);
});

test('validation failures are wrapped in the envelope', function () {
    $this->postJson('/api/v1/auth/tokens', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 422)
        ->assertJsonStructure(['code', 'message', 'timestamp', 'data' => ['errors' => ['email', 'password']]]);
});

test('unknown api routes render json instead of an html error page', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('code', 404)
        ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);
});

test('throttled api requests keep the envelope', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/tokens', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->postJson('/api/v1/auth/tokens', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(429)
        ->assertHeader('Retry-After', '60')
        ->assertHeader('X-RateLimit-Limit', '5')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertJsonPath('code', 429)
        ->assertJsonPath('message', '请求过于频繁，请稍后再试')
        ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);
});

test('returns 429 when an authenticated user exceeds the api rate limit', function () {
    config(['app.api_rate_limit' => 2]);

    $first = User::factory()->create();
    $second = User::factory()->create();

    bearer($first)->getJson('/api/v1/me')->assertOk();
    bearer($first)->getJson('/api/v1/me')->assertOk();
    bearer($first)->getJson('/api/v1/me')->assertTooManyRequests();

    bearer($second)->getJson('/api/v1/me')->assertOk();
});

test('returns 429 when one ip exceeds the login attempt limit', function () {
    config(['app.login_ip_rate_limit' => 2]);

    foreach (['first@mes.test', 'second@mes.test'] as $email) {
        $this->postJson('/api/v1/auth/tokens', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/tokens', ['email' => 'third@mes.test', 'password' => 'wrong'])
        ->assertTooManyRequests();
});

test('method not allowed responses preserve allowed methods', function () {
    $this->postJson('/api/v1/me')
        ->assertStatus(405)
        ->assertHeader('Allow', 'GET, HEAD')
        ->assertJsonPath('code', 405);
});

test('queue, cache and mqtt read their connection settings from env', function () {
    expect(config('queue.default'))->toBe(env('QUEUE_CONNECTION'))
        ->and(config('cache.default'))->toBe(env('CACHE_STORE'))
        ->and(config('queue.connections.redis.connection'))->toBe('default')
        ->and(config('mqtt'))->toHaveKeys(['host', 'port', 'factory']);
});

test('paginated collections keep items and meta inside the envelope', function () {
    User::factory()->count(3)->create();

    $paginator = User::query()->paginate(2);
    $response = ApiResponse::paginated($paginator, UserResource::class);

    $data = $response->getData(true);

    expect($data['code'])->toBe(200)
        ->and($data['data']['meta'])->toBe(['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2])
        ->and($data['data']['items'])->toHaveCount(2);
});
