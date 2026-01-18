<?php

namespace App\Http\Controllers\V1\Customer;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Customer Registration
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:15',
            'address_line_1' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postal_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => UserType::CUSTOMER,
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

            DB::commit();

            // Generate token
            $token = auth()->login($user);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => auth()->factory()->getTTL() * 60,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'user_type' => $user->user_type->value
                    ]
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
        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = auth()->user();

        // SECURITY CHECK 1: Verify user type
        if ($user->user_type !== UserType::CUSTOMER) {
            auth()->logout();
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Please use admin login portal.'
            ], 403);
        }

        // SECURITY CHECK 2: Verify user can login
        if (!$user->canLogin()) {
            auth()->logout();
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.'
            ], 403);
        }

        // Update last login
        $user->updateLastLogin($request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'user_type' => $user->user_type->value
                ]
            ]
        ]);
    }

    /**
     * Get authenticated customer user
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'user_type' => $user->user_type->value,
                'profile_image' => $user->profile_image,
                'last_login_at' => $user->last_login_at,
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
     * Customer Logout
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }
}
