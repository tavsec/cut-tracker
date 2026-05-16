<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $key = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many login attempts.'], 429);
        }

        $password = $request->input('password', '');
        $hash = config('app.password_hash');

        if (! $hash || ! Hash::check($password, $hash)) {
            RateLimiter::hit($key, 60);

            return response()->json(['message' => 'Invalid password.'], 401);
        }

        RateLimiter::clear($key);

        $user = User::first();
        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api', ['*'], now()->addDays(30));

        return response()->json(['token' => $token->plainTextToken]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(): JsonResponse
    {
        return response()->json(['authenticated' => true]);
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
