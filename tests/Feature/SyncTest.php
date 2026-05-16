<?php

use App\Models\Day;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('sync requires authentication', function () {
    $this->postJson('/api/sync', ['ops' => []])->assertUnauthorized();
});

test('sync applies a batch of put ops', function () {
    $this->withToken($this->token)->postJson('/api/sync', [
        'ops' => [
            ['type' => 'put', 'date' => '2026-01-01', 'data' => ['kcal' => 2000]],
            ['type' => 'put', 'date' => '2026-01-02', 'data' => ['kcal' => 1800]],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'results')
        ->assertJsonPath('results.0.success', true)
        ->assertJsonPath('results.1.success', true);

    $this->assertDatabaseHas('days', ['date' => '2026-01-01', 'kcal' => 2000]);
    $this->assertDatabaseHas('days', ['date' => '2026-01-02', 'kcal' => 1800]);
});

test('sync applies delete ops', function () {
    Day::factory()->create(['date' => '2026-01-01']);

    $this->withToken($this->token)->postJson('/api/sync', [
        'ops' => [
            ['type' => 'delete', 'date' => '2026-01-01'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('results.0.success', true);

    $this->assertDatabaseMissing('days', ['date' => '2026-01-01']);
});

test('sync returns per-op results for mix of ops', function () {
    $this->withToken($this->token)->postJson('/api/sync', [
        'ops' => [
            ['type' => 'put', 'date' => '2026-01-01', 'data' => ['kcal' => 2000]],
            ['type' => 'put', 'date' => '2026-01-01', 'data' => ['kcal' => 1800]],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(2, 'results');
});
