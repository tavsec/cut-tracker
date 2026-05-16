<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Day;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    public function export(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');

        return response()->json([
            'exported_at' => now()->toISOString(),
            'settings' => $settings,
            'days' => Day::orderBy('date')->get(),
        ]);
    }
}
