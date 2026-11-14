<?php

use App\Models\User;

test('me requires authentication', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('a user can obtain a bearer token', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'web',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token']);

    expect($user->tokens()->count())->toBe(1);
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
        ->assertJsonPath('data.id', $user->id);
});

test('a bearer token can be revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('/api/v1/auth/tokens')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
