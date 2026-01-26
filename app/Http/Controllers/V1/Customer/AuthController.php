<?php

namespace App\Http\Controllers\V1\Customer;

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
            Customer::create([
                'user_id' => $user->id,
                'phone' => $data["phone"],
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

        $credentials = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? ['email' => $request->login, 'password' => $request->password]
            : ['username' => $request->login, 'password' => $request->password];

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = auth('api')->user();

        if ($user->user_type !== 'customer') {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Please use customer login portal.'
            ], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before logging in.',
                'email_verified' => false,
                'email' => $user->email
            ], 403);
        }

        if (!$user->canLogin()) {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.'
            ], 403);
        }

        $user->updateLastLogin($request->ip());

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
        ]);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            $user = auth('api')->user();
            $customer = $user->customer;

            if (!$user || !$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Profile not found'
                ], 404);
            }

            $userData = [];
            if ($request->hasFile('profile_image')) {
                $userData['profile_image'] = $this->handleFileUpload(
                    $request,
                    'profile_image',
                    $user->profile_image,
                    'profiles/customers/',
                    $user->username ?? 'customer_' . $user->id
                );
            }

            if (!empty($data['password'])) {
                if (!Hash::check($data['current_password'], $user->password)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Current password does not match'
                    ], 400);
                }
                $userData['password'] = Hash::make($data['password']);
            }

            $userData['name'] = $data['name'] ?? $user->name;
            $userData['username'] = $data['username'] ?? $user->username;
            $userData['email'] = $data['email'] ?? $user->email;

            $user->update($userData);
            $customer->update($request->only([
                'phone',
                'have_whatsapp',
                'whatsapp_number',
                'address_line_1',
                'address_line_2',
                'landmark',
                'city',
                'state',
                'country',
                'postal_code'
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $this->me()->original['data']
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
        $request->validate(['token' => 'required|string']);

        $user = User::where('email_verification_token', $request->token)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid verification token.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => false, 'message' => 'Email already verified.'], 400);
        }

        if (!$user->isEmailVerificationTokenValid($request->token)) {
            return response()->json(['success' => false, 'message' => 'Token has expired.'], 400);
        }

        $user->markEmailAsVerifiedcheck($request->token);
        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully!',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => $user
            ]
        ]);
    }

    /**
     * Resend email verification link
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->where('user_type', 'customer')->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['success' => false, 'message' => 'Email already verified.'], 400);
        }

        $token = $user->generateEmailVerificationToken();
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;
        Mail::to($user->email)->send(new CustomerWelcomeMail($user->name, $verificationUrl));

        return response()->json(['success' => true, 'message' => 'Verification email sent successfully!']);
    }

    /**
     * Customer Logout
     */
    public function logout(Request $request)
    {
        try {
            Auth::guard('api')->logout();
            return response()->json(['status' => 'success', 'message' => 'Logout successful'], 200);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'error', 'message' => 'Failed to logout'], 500);
        }
    }

    /**
     * Redirect to Google
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google Callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = $this->findOrCreateGoogleUser($googleUser);

            if ($user instanceof JsonResponse) {
                return $user;
            }

            $token = Auth::guard('api')->login($user);
            $user->updateLastLogin(request()->ip());

            $redirectUrl = config('app.frontend_url') . '/auth/callback?token=' . $token . '&type=bearer';
            return redirect($redirectUrl);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Google login failed', 'error' => $e->getMessage()], 401);
        }
    }

    /**
     * Authenticate using a Google access token from the frontend
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate(['access_token' => 'required|string']);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->access_token);
            $user = $this->findOrCreateGoogleUser($googleUser);

            if ($user instanceof JsonResponse) {
                return $user;
            }

            if (!$token = Auth::guard('api')->login($user)) {
                return response()->json(['success' => false, 'message' => 'Failed to generate token'], 500);
            }

            $user->updateLastLogin($request->ip());

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                    'user' => $user
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Google authentication failed', 'error' => $e->getMessage()], 401);
        }
    }

    /**
     * Helper to find or create a user from Google data
     */
    private function findOrCreateGoogleUser($googleUser)
    {
        $user = User::where('google_id', $googleUser->getId())->orWhere('email', $googleUser->getEmail())->first();

        if (!$user) {
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
                    'profile_image' => $googleUser->getAvatar(),
                    'google_id' => $googleUser->getId(),
                    'auth_provider' => 'google'
                ]);

                Customer::create(['user_id' => $user->id, 'is_verified' => true, 'verification_level' => 'basic']);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Failed to create user', 'error' => $e->getMessage()], 500);
            }
        } else {
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId(), 'auth_provider' => 'google']);
            }

            if (!$user->isCustomer()) {
                return response()->json(['success' => false, 'message' => 'Email associated with another account type.'], 403);
            }

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        }

        return $user;
    }

    /**
     * Link Google account to existing user
     */
    public function linkGoogleAccount(Request $request)
    {
        $request->validate(['access_token' => 'required|string']);

        try {
            $user = Auth::user();
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->access_token);

            $existingAccount = SocialAccount::where('provider', 'google')
                ->where('provider_id', $googleUser->getId())
                ->where('user_id', '!=', $user->id)
                ->first();

            if ($existingAccount) {
                return response()->json(['success' => false, 'message' => 'Google account already linked to another user'], 409);
            }

            SocialAccount::updateOrCreate(
                ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => $googleUser->getId()],
                [
                    'token' => $googleUser->token,
                    'refresh_token' => $googleUser->refreshToken,
                    'expires_at' => now()->addSeconds($googleUser->expiresIn),
                    'avatar' => $googleUser->getAvatar(),
                    'provider_data' => json_encode($googleUser->user),
                ]
            );

            return response()->json(['success' => true, 'message' => 'Google account linked successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to link Google account', 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Unlink Google account
     */
    public function unlinkGoogleAccount(Request $request)
    {
        $user = Auth::guard('api')->user();
        $socialAccount = SocialAccount::where('user_id', $user->id)->where('provider', 'google')->first();

        if (!$socialAccount) {
            return response()->json(['success' => false, 'message' => 'No Google account linked'], 404);
        }

        if (empty($user->password)) {
            return response()->json(['success' => false, 'message' => 'Please set a password before unlinking'], 400);
        }

        $socialAccount->delete();
        return response()->json(['success' => true, 'message' => 'Google account unlinked successfully']);
    }
}
