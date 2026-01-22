<?php

namespace App\Http\Controllers\V1\Customer;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRegistrationRequest;
use App\Mail\CustomerWelcomeMail;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Customer Registration with Email Verification
     */
    public function register(CustomerRegistrationRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => 'customer',
                'is_active' => true,
                'can_login' => true
            ]);

            // Create customer profile
            $customer = Customer::create([
                'user_id' => $user->id,
                'phone' => $request->phone,
                'have_whatsapp' => $request->have_whatsapp ?? false,
                'whatsapp_number' => $request->whatsapp_number,
                'address_line_1' => $request->address_line_1,
                'address_line_2' => $request->address_line_2,
                'landmark' => $request->landmark,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country ?? 'Sri Lanka',
                'postal_code' => $request->postal_code,
            ]);

            // Generate email verification token
            $token = $user->generateEmailVerificationToken();

            // Create verification URL pointing to frontend
            $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;

            // Send welcome email with verification link
            Mail::to($user->email)->send(new CustomerWelcomeMail($user->name, $verificationUrl));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Please check your email to verify your account.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'email_verified' => false
                    ],
                    'verification_sent' => true
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Customer Login
     * Only users with user_type = 'customer' can login here
     * Email verification is required
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Determine if login is email or username
        $credentials = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? ['email' => $request->login, 'password' => $request->password]
            : ['username' => $request->login, 'password' => $request->password];

        // Attempt authentication
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = auth('api')->user();

        // SECURITY CHECK 1: Verify user type
        if ($user->user_type !== 'customer') {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Please use customer login portal.'
            ], 403);
        }

        // SECURITY CHECK 2: Verify email is verified
        if (!$user->hasVerifiedEmail()) {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.',
                'email_verified' => false,
                'email' => $user->email
            ], 403);
        }

        // SECURITY CHECK 3: Verify user can login
        if (!$user->canLogin()) {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.'
            ], 403);
        }

        // Update last login
        $user->updateLastLogin($request->ip());

        // Create secure HTTP-only cookie
        $cookie = cookie(
            'auth_token',
            $token,
            60 * 24 * 7, // 7 days
            '/',
            null,
            true,  // Secure
            true,  // HttpOnly
            false,
            'lax'
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'user_type' => $user->user_type,
                    'email_verified' => true
                ]
            ]
        ])->cookie($cookie);
    }

    /**
     * Get authenticated customer user
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        $customer = $user->customer;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'user_type' => $user->user_type,
                'profile_image' => $user->profile_image,
                'last_login_at' => $user->last_login_at,
                'email_verified' => $user->hasVerifiedEmail(),
                'customer_profile' => $customer ? [
                    'phone' => $customer->phone,
                    'whatsapp_contact' => $customer->whatsapp_contact,
                    'address' => $customer->full_address,
                    'is_verified' => $customer->is_verified,
                    'verification_level' => $customer->verification_level,
                ] : null
            ]
        ]);
    }

    /**
     * Verify customer email address
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user by verification token
        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token.'
            ], 400);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified. You can login now.'
            ], 400);
        }

        // Validate token expiration
        if (!$user->isEmailVerificationTokenValid($request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Verification token has expired. Please request a new verification email.',
                'token_expired' => true
            ], 400);
        }

        // Mark email as verified
        $user->markEmailAsVerified();

        // Auto-login user after verification
        $token = Auth::guard('api')->login($user);

        // Create secure HTTP-only cookie
        $cookie = cookie(
            'auth_token',
            $token,
            60 * 24 * 7, // 7 days
            '/',
            null,
            true,  // Secure
            true,  // HttpOnly
            false,
            'lax'
        );

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully! You are now logged in.',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'user_type' => $user->user_type,
                    'email_verified' => true
                ]
            ]
        ])->cookie($cookie);
    }

    /**
     * Resend email verification link
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user by email
        $user = User::where('email', $request->email)
            ->where('user_type', 'customer')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.'
            ], 404);
        }

        // Check if already verified
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified. You can login now.'
            ], 400);
        }

        // Generate new verification token
        $token = $user->generateEmailVerificationToken();

        // Create verification URL
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;

        // Send verification email
        Mail::to($user->email)->send(new CustomerWelcomeMail($user->name, $verificationUrl));

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent successfully! Please check your inbox.',
            'email' => $user->email
        ]);
    }

    /**
     * Customer Logout
     */
    public function logout(Request $request)
    {
        try {

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
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
