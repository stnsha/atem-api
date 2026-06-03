<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * POST /api/login
     *
     * Body: { "email": "...", "password": "..." }
     * Returns: { success, data: { access_token, token_type, expires_in } }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $token = Auth::guard('api')->attempt($credentials);

            if (!$token) {
                Log::info('AuthController: Login failed', [
                    'email' => $credentials['email'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }
        } catch (JWTException $e) {
            Log::error('AuthController: JWT exception during login', [
                'email' => $credentials['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not create token',
            ], 500);
        }

        Log::info('AuthController: Login succeeded', [
            'email' => $credentials['email'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            ],
        ]);
    }

    /**
     * POST /api/logout (requires auth:api)
     */
    public function logout(): JsonResponse
    {
        try {
            Auth::guard('api')->logout();

            return response()->json([
                'success' => true,
                'message' => 'Logged out',
            ]);
        } catch (JWTException $e) {
            Log::error('AuthController: JWT exception during logout', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not invalidate token',
            ], 500);
        }
    }

    /**
     * GET /api/me (requires auth:api)
     */
    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => Auth::guard('api')->user(),
        ]);
    }
}
