<?php

namespace Database\Factories;

use App\Models\Day;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Day>
 */
class DayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'weight_kg' => $this->faker->randomFloat(2, 60, 120),
            'kcal' => $this->faker->numberBetween(1500, 3000),
            'protein_g' => $this->faker->numberBetween(100, 250),
            'carbs_g' => $this->faker->numberBetween(100, 400),
            'fat_g' => $this->faker->numberBetween(40, 100),
            'steps' => $this->faker->numberBetween(2000, 20000),
            'sleep_hours' => $this->faker->randomFloat(1, 4, 10),
            'hunger' => $this->faker->numberBetween(1, 5),
            'energy' => $this->faker->numberBetween(1, 5),
            'refeed' => false,
            'session' => $this->faker->randomElement(['Push', 'Pull', 'Legs', 'Other', null]),
            'rpe' => $this->faker->randomFloat(1, 5, 10),
            'photos_taken' => false,
        ];
    }
}
