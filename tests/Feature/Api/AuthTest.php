<?php

use App\Modules\System\Model\User;

test('persisted user token types can authenticate and be revoked', function (string $modelType) {
    $user = User::factory()->create();
    $issued = $user->createToken('web');
    $issued->accessToken->forceFill(['tokenable_type' => $modelType])->save();

    $this->withToken($issued->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    app('auth')->forgetGuards();

    $this->withToken($issued->plainTextToken)
        ->deleteJson('/api/v1/auth/tokens')
        ->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $issued->accessToken->id]);

    app('auth')->forgetGuards();

    $this->withToken($issued->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
})->with(['legacy' => 'App\\Models\\User', 'modular' => User::class]);

test('me requires authentication', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401)
        ->assertJsonStructure(['code', 'message', 'data', 'timestamp']);
});

test('a user can obtain a bearer token', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'web',
    ]);

    $response->assertOk()
        ->assertJsonPath('code', 200)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure([
            'code',
            'message',
            'timestamp',
            'data' => ['token', 'user' => ['id', 'name', 'email']],
        ]);

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->tokenable_type)->toBe(User::class);
});

test('invalid credentials are rejected', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

test('a bearer token can access the current user', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('message', 'success');
});

test('a bearer token can be revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/tokens')
        ->assertOk()
        ->assertJsonPath('code', 200);

    expect($user->tokens()->count())->toBe(0);
});

test('a disabled user is rejected at login', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $user->update(['status' => false]);

    $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

test('a token issued before the user was disabled stops working', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;
    $user->update(['status' => false]);

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});
