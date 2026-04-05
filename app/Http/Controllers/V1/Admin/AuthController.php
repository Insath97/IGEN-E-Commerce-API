<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;
    /**
     * Admin Login
     * Only users with user_type = 'admin' can login here
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $credentials = $request->only('email', 'password');

            if (!$token = Auth::guard('api')->attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user = auth('api')->user();

            // SECURITY CHECK 1: Verify user type
            if ($user->user_type !== 'admin') {
                Auth::guard('api')->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin credentials required.'
                ], 403);
            }

            if (!$user->canLogin()) {
                Auth::guard('api')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is deactivated'
                ], 401);
            }

            if (!$user->roles()->exists()) {
                Auth::guard('api')->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'No admin role assigned. Please contact Super Admin.'
                ], 403);
            }

            $user->updateLastLogin($request->ip());

            $cookie = cookie(
                'auth_token',
                $token,
                60 * 24 * 7,
                '/',
                null,
                true,  // Secure
                true,  // HttpOnly
                false,
                'lax'
            );

            $user->load(['roles' => function ($query) {
                $query->select('id', 'name')
                    ->with(['permissions' => function ($query) {
                        $query->select('id', 'name');
                    }]);
            }]);

            if ($user->relationLoaded('roles')) {
                $user->roles->each->makeHidden(['pivot']);
                $user->roles->each(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        $role->permissions->each->makeHidden(['pivot']);
                    }
                });
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'auth_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 200)->cookie($cookie);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to login',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Admin Logout
     */
    public function logout(Request $request)
    {
        try {
            $user = auth('api')->user();
            if ($user) {
                $this->logActivity('Auth', 'Logout', "Admin logged out: {$user->email}");
            }

            // Logout the user (invalidates the token)
            Auth::guard('api')->logout();

            // Create an expired cookie to remove it from browser
            $cookie = Cookie::forget('auth_token');

            return response()->json([
                'status' => 'success',
                'message' => 'Logout successful'
            ], 200)->withCookie($cookie);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to logout',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get authenticated admin user
     */
    public function me()
    {
        try {
            $user = auth('api')->user();

            $user->load(['roles' => function ($query) {
                $query->select('id', 'name')
                    ->with(['permissions' => function ($query) {
                        $query->select('id', 'name');
                    }]);
            }]);

            if ($user->relationLoaded('roles')) {
                $user->roles->each->makeHidden(['pivot']);
                $user->roles->each(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        $role->permissions->each->makeHidden(['pivot']);
                    }
                });
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User details fetched successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user details',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    /**
     * Admin Forgot Password - Send reset link
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->where('user_type', 'admin')->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Admin account not found with this email.'
                ], 404);
            }

            $token = $user->generatePasswordResetToken();
            $resetUrl = config('app.admin_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

            Mail::to($user->email)->send(new ForgotPasswordMail($user->name, $resetUrl));

            $this->logActivity(
                'Auth',
                'Admin Forgot Password Request',
                'Password reset link sent to admin: ' . $user->email,
                ['email' => $user->email]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link has been sent to your admin email.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send reset link',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Admin Reset Password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = User::where('email', $request->email)->where('user_type', 'admin')->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Admin account not found.'
                ], 404);
            }

            if (!$user->isPasswordResetTokenValid($request->token)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired reset token.'
                ], 400);
            }

            $user->markPasswordAsReset($request->password);

            $this->logActivity(
                'Auth',
                'Admin Reset Password',
                'Admin password reset successful for: ' . $user->email,
                ['email' => $user->email]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Admin password has been reset successfully. You can now login to the admin panel.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset admin password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
