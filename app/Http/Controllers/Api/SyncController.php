<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'ops' => 'required|array',
            'ops.*.type' => 'required|in:put,delete',
            'ops.*.date' => 'required|date_format:Y-m-d',
            'ops.*.data' => 'nullable|array',
        ]);

        $results = [];

        foreach ($request->input('ops') as $op) {
            try {
                if ($op['type'] === 'put') {
                    $day = Day::updateOrCreate(
                        ['date' => $op['date']],
                        $op['data'] ?? []
                    );
                    $results[] = ['date' => $op['date'], 'success' => true, 'day' => $day];
                } elseif ($op['type'] === 'delete') {
                    Day::where('date', $op['date'])->delete();
                    $results[] = ['date' => $op['date'], 'success' => true];
                }
            } catch (Throwable $e) {
                $results[] = ['date' => $op['date'], 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }
}
