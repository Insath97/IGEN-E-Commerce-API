<?php

namespace App\Http\Controllers\V1\Customer;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRegistrationRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Mail\CustomerWelcomeMail;
use App\Models\Customer;
use App\Models\SocialAccount;
use App\Models\User;
use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    use FileUploadTrait;
    /**
     * Customer Registration with Email Verification
     */
    public function register(CustomerRegistrationRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            $data = $request->validated();
            // Create user
            $user = User::create([
                'name' => $data["name"],
                'username' => $data["username"],
                'email' => $data["email"],
                'password' => Hash::make($data["password"]),
                'user_type' => 'customer',
                'is_active' => true,
                'can_login' => true
            ]);

            // Create customer profile
            $customer = Customer::create([
                'user_id' => $user->id,
                'phone' =>$data["phone"],
                'have_whatsapp' => $data["have_whatsapp"] ?? false,
                'whatsapp_number' => $data["whatsapp_number"],
                'address_line_1' => $data["address_line_1"],
                'address_line_2' => $data["address_line_2"],
                'landmark' => $data["landmark"],
                'city' => $data["city"],
                'state' => $data["state"],
                'country' => $data["country"] ?? 'Sri Lanka',
                'postal_code' => $data["postal_code"],
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
     * Update customer profile
     */
    public function updateProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = auth('api')->user();
            $customer = $user->customer;

            // Handle User data
            $userData = $request->only(['name', 'username', 'email']);

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $imagePath = $this->handleFileUpload(
                    $request,
                    'profile_image',
                    $user->profile_image,
                    'profiles/customers/',
                    $user->username ?? 'customer_' . $user->id
                );
                $userData['profile_image'] = $imagePath;
            }

            if (!empty($userData)) {
                $user->update($userData);
            }

            // Handle Customer profile data
            $customerData = $request->only([
                'phone', 'have_whatsapp', 'whatsapp_number',
                'address_line_1', 'address_line_2', 'landmark',
                'city', 'state', 'country', 'postal_code'
            ]);

            if ($customer) {
                $customer->update($customerData);
            } else {
                // Should not normally happen if registered correctly, but for safety:
                $customerData['user_id'] = $user->id;
                $customer = Customer::create($customerData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'profile_image' => $user->profile_image,
                    'customer_profile' => [
                        'phone' => $customer->phone,
                        'whatsapp_number' => $customer->whatsapp_number,
                        'have_whatsapp' => $customer->have_whatsapp,
                        'address_line_1' => $customer->address_line_1,
                        'address_line_2' => $customer->address_line_2,
                        'landmark' => $customer->landmark,
                        'city' => $customer->city,
                        'state' => $customer->state,
                        'country' => $customer->country,
                        'postal_code' => $customer->postal_code,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Cleanup uploaded files if error occurs
            if (isset($imagePath)) {
                $this->deleteFile($imagePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
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
        $user->markEmailAsVerifiedcheck($request->token);

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

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google Callback
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Check if user exists
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                // Register new user
                DB::beginTransaction();
                try {
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'username' => 'google_' . $googleUser->getId(),
                        'email' => $googleUser->getEmail(),
                        'password' => Hash::make(str()->random(24)),
                        'user_type' => 'customer',
                        'email_verified_at' => now(),
                        'is_active' => true,
                        'can_login' => true,
                        'google_id' => $googleUser->getId(),
                        'auth_provider' => 'google'
                    ]);

                    // Create minimal customer profile
                    Customer::create([
                        'user_id' => $user->id,
                        'is_verified' => true,
                        'verification_level' => 'basic'
                    ]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Google authentication failed',
                        'error' => $e->getMessage()
                    ], 401);
                }
            } else {
                // Update existing user's google_id if missing
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'auth_provider' => 'google',
                        // verification? If they logged in via Google, we can trust the email.
                        // But if they had an unverified account, we might verification.
                        // Let's mark verified if not already.
                    ]);

                    if (!$user->hasVerifiedEmail()) {
                        $user->markEmailAsVerified();
                    }
                }

                // Ensure customer type
                if ($user->user_type !== 'customer') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email is associated with a ' . $user->user_type . ' account, not a customer account.'
                    ], 403);
                }
            }

            // Login
            $token = Auth::guard('api')->login($user);
            $user->updateLastLogin(request()->ip());

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

            // Verify if the request is from a frontend client expecting a redirect
            // For now, we assume this flow is always for the frontend.

            $frontendUrl = config('app.frontend_url');

            // Append token to frontend URL
            // Ensure your frontend has a route to handle this: /auth/google/callback?token=...
            $redirectUrl = $frontendUrl . '/auth/google/callback?token=' . $token . '&role=customer' . '&verified=true';

            return redirect($redirectUrl)->withCookie($cookie);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Link Google account to existing user
     */
    public function linkGoogleAccount(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        try {
            $user = Auth::user();

            // Get Google user data from access token
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);

            // Check if Google account is already linked to another user
            $existingAccount = SocialAccount::where('provider', 'google')
                ->where('provider_id', $googleUser->getId())
                ->where('user_id', '!=', $user->id)
                ->first();

            if ($existingAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Google account is already linked to another user'
                ], 409);
            }

            // Link Google account
            $this->createOrUpdateSocialAccount($user, $googleUser);

            return response()->json([
                'success' => true,
                'message' => 'Google account linked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to link Google account',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Unlink Google account
     */
    public function unlinkGoogleAccount(Request $request)
    {
        $user = Auth::guard('api')->user();

        $socialAccount = SocialAccount::where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        if (!$socialAccount) {
            return response()->json([
                'success' => false,
                'message' => 'No Google account linked'
            ], 404);
        }

        // Check if user has password (can't unlink if no password)
        if (Hash::check('', $user->password) || $user->password === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please set a password before unlinking Google account'
            ], 400);
        }

        $socialAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Google account unlinked successfully'
        ]);
    }

    /**
     * Create or update social account
     */
    private function createOrUpdateSocialAccount($user, $googleUser)
    {
        SocialAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_id' => $googleUser->getId(),
            ],
            [
                'token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken,
                'expires_at' => now()->addSeconds($googleUser->expiresIn),
                'avatar' => $googleUser->getAvatar(),
                'provider_data' => json_encode($googleUser->user),
            ]
        );
    }
}
