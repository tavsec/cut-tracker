<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api')->plainTextToken;
});

test('unauthenticated settings requests get 401', function () {
    $this->getJson('/api/settings')->assertUnauthorized();
    $this->putJson('/api/settings', [])->assertUnauthorized();
});

test('index returns all keys with nulls for unset values', function () {
    $this->withToken($this->token)->getJson('/api/settings')
        ->assertOk()
        ->assertJson([
            'start_date' => null,
            'kcal_target' => null,
            'protein_target' => null,
        ]);
});

test('update stores and returns merged settings', function () {
    $this->withToken($this->token)
        ->putJson('/api/settings', ['kcal_target' => 2200, 'protein_target' => 180])
        ->assertOk()
        ->assertJsonPath('kcal_target', '2200')
        ->assertJsonPath('protein_target', '180')
        ->assertJsonPath('start_date', null);
});

test('update is a partial merge, not a full replacement', function () {
    $this->withToken($this->token)->putJson('/api/settings', ['kcal_target' => 2200]);
    $this->withToken($this->token)->putJson('/api/settings', ['protein_target' => 180]);

    $this->withToken($this->token)->getJson('/api/settings')
        ->assertOk()
        ->assertJsonPath('kcal_target', '2200')
        ->assertJsonPath('protein_target', '180');
});
