<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const KEYS = ['start_date', 'kcal_target', 'protein_target'];

    public function index(): JsonResponse
    {
        $settings = Setting::whereIn('key', self::KEYS)->pluck('value', 'key');

        return response()->json(array_merge(
            array_fill_keys(self::KEYS, null),
            $settings->toArray()
        ));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'kcal_target' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0',
        ]);

        foreach (array_intersect_key($validated, array_flip(self::KEYS)) as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return $this->index();
    }
}
