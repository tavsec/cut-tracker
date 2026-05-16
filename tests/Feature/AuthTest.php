<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['app.password_hash' => Hash::make('secret')]);
    User::factory()->create(['email' => 'owner@cut-tracker.local']);
});

test('health endpoint returns 200 without auth', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

test('login returns token with correct password', function () {
    $this->postJson('/api/login', ['password' => 'secret'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('login returns 401 with wrong password', function () {
    $this->postJson('/api/login', ['password' => 'wrong'])
        ->assertUnauthorized();
});

test('login is rate limited after 5 attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', ['password' => 'wrong']);
    }

    $this->postJson('/api/login', ['password' => 'wrong'])
        ->assertStatus(429);
});

test('me returns authenticated true with valid token', function () {
    $user = User::first();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/me')
        ->assertOk()
        ->assertJson(['authenticated' => true]);
});

test('me returns 401 without token', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

test('logout revokes token', function () {
    $user = User::first();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});
