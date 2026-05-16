<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'weight_kg',
        'kcal',
        'protein_g',
        'carbs_g',
        'fat_g',
        'steps',
        'sleep_hours',
        'hunger',
        'energy',
        'refeed',
        'session',
        'rpe',
        'lifts',
        'notes',
        'waist_cm',
        'photos_taken',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'weight_kg' => 'decimal:2',
            'sleep_hours' => 'decimal:1',
            'rpe' => 'decimal:1',
            'waist_cm' => 'decimal:1',
            'refeed' => 'boolean',
            'photos_taken' => 'boolean',
        ];
    }
}
