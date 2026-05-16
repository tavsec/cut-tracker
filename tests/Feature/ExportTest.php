<?php

use App\Models\Day;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('export requires authentication', function () {
    $this->getJson('/api/export')->assertUnauthorized();
});

test('export returns correct shape', function () {
    Day::factory()->create(['date' => '2026-01-01']);
    Setting::create(['key' => 'kcal_target', 'value' => '2200']);

    $this->withToken($this->token)->getJson('/api/export')
        ->assertOk()
        ->assertJsonStructure(['exported_at', 'settings', 'days'])
        ->assertJsonCount(1, 'days')
        ->assertJsonPath('days.0.date', '2026-01-01');
});

test('export days are sorted ascending by date', function () {
    Day::factory()->create(['date' => '2026-01-03']);
    Day::factory()->create(['date' => '2026-01-01']);

    $response = $this->withToken($this->token)->getJson('/api/export')->assertOk();
    expect($response->json('days.0.date'))->toBe('2026-01-01');
    expect($response->json('days.1.date'))->toBe('2026-01-03');
});
