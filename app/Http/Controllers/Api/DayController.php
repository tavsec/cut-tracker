<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class DayController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Day::orderBy('date')->get());
    }

    public function show(string $date): JsonResponse
    {
        $day = Day::where('date', $date)->first();

        if (! $day) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($day);
    }

    public function upsert(Request $request, string $date): JsonResponse
    {
        if (! $this->isValidDate($date)) {
            return response()->json(['message' => 'Invalid date format.'], 422);
        }

        $validated = $request->validate([
            'weight_kg' => 'nullable|numeric|between:0,999.99',
            'kcal' => 'nullable|integer|min:0',
            'protein_g' => 'nullable|integer|min:0',
            'carbs_g' => 'nullable|integer|min:0',
            'fat_g' => 'nullable|integer|min:0',
            'steps' => 'nullable|integer|min:0',
            'sleep_hours' => 'nullable|numeric|between:0,24',
            'hunger' => 'nullable|integer|between:1,5',
            'energy' => 'nullable|integer|between:1,5',
            'refeed' => 'nullable|boolean',
            'session' => ['nullable', Rule::in(['Push', 'Pull', 'Legs', 'Other'])],
            'rpe' => 'nullable|numeric|between:0,10',
            'lifts' => 'nullable|string',
            'notes' => 'nullable|string',
            'waist_cm' => 'nullable|numeric|between:0,999.9',
            'photos_taken' => 'nullable|boolean',
        ]);

        $day = Day::updateOrCreate(
            ['date' => $date],
            $request->only(array_keys($validated))
        );

        return response()->json($day);
    }

    public function destroy(string $date): Response
    {
        Day::where('date', $date)->delete();

        return response()->noContent();
    }

    private function isValidDate(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = explode('-', $date);

        return checkdate((int) $month, (int) $day, (int) $year);
    }
}
