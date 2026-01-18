<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthMiddleware
{
    /**
     * Handle an incoming request.
     * Ensures only customer users can access customer routes
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::auth('api')->user();

        // CRITICAL: Verify user_type from token claims
        $tokenUserType = Auth::auth('api')->payload()->get('user_type');

        if ($tokenUserType !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer authentication required.'
            ], 403);
        }

        // Double-check database user_type
        if ($user->user_type !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer account required.'
            ], 403);
        }

        // Verify user is active
        if (!$user->canLogin()) {
            return response()->json([
                'success' => false,
                'message' => 'Account deactivated'
            ], 403);
        }

        return $next($request);
    }
}
