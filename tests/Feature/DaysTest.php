<?php

use App\Models\Day;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('unauthenticated requests get 401', function () {
    $this->getJson('/api/days')->assertUnauthorized();
    $this->putJson('/api/days/2026-01-01', [])->assertUnauthorized();
    $this->deleteJson('/api/days/2026-01-01')->assertUnauthorized();
});

test('index returns all days sorted ascending', function () {
    Day::factory()->create(['date' => '2026-01-03']);
    Day::factory()->create(['date' => '2026-01-01']);
    Day::factory()->create(['date' => '2026-01-02']);

    $this->withToken($this->token)->getJson('/api/days')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.date', '2026-01-01')
        ->assertJsonPath('2.date', '2026-01-03');
});

test('upsert creates new day', function () {
    $this->withToken($this->token)
        ->putJson('/api/days/2026-01-15', ['kcal' => 2000, 'protein_g' => 150])
        ->assertOk()
        ->assertJsonPath('kcal', 2000)
        ->assertJsonPath('protein_g', 150)
        ->assertJsonPath('date', '2026-01-15');

    $this->assertDatabaseHas('days', ['date' => '2026-01-15', 'kcal' => 2000]);
});

test('upsert updates existing day without wiping other fields', function () {
    Day::factory()->create(['date' => '2026-01-15', 'kcal' => 2000, 'protein_g' => 150, 'weight_kg' => 80.5]);

    $this->withToken($this->token)
        ->putJson('/api/days/2026-01-15', ['kcal' => 1800])
        ->assertOk()
        ->assertJsonPath('kcal', 1800)
        ->assertJsonPath('protein_g', 150)
        ->assertJsonPath('weight_kg', '80.50');
});

test('show returns 404 for missing day', function () {
    $this->withToken($this->token)->getJson('/api/days/2026-01-15')->assertNotFound();
});

test('delete removes a day and returns 204', function () {
    Day::factory()->create(['date' => '2026-01-15']);

    $this->withToken($this->token)->deleteJson('/api/days/2026-01-15')->assertNoContent();
    $this->assertDatabaseMissing('days', ['date' => '2026-01-15']);
});

test('invalid date format returns 422', function () {
    $this->withToken($this->token)->putJson('/api/days/2025-13-99', [])->assertStatus(422);
    $this->withToken($this->token)->putJson('/api/days/not-a-date', [])->assertStatus(422);
});
