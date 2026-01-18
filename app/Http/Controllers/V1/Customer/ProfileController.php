<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get customer profile
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'profile_image' => $user->profile_image,
                ],
                'profile' => [
                    'phone' => $customer->phone,
                    'have_whatsapp' => $customer->have_whatsapp,
                    'whatsapp_number' => $customer->whatsapp_number,
                    'whatsapp_contact' => $customer->whatsapp_contact,
                    'address_line_1' => $customer->address_line_1,
                    'address_line_2' => $customer->address_line_2,
                    'landmark' => $customer->landmark,
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'country' => $customer->country,
                    'postal_code' => $customer->postal_code,
                    'full_address' => $customer->full_address,
                    'is_verified' => $customer->is_verified,
                    'verified_at' => $customer->verified_at,
                    'verification_level' => $customer->verification_level,
                ]
            ]
        ]);
    }

    /**
     * Update customer profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer profile not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:15',
            'have_whatsapp' => 'sometimes|boolean',
            'whatsapp_number' => 'nullable|string|max:15',
            'address_line_1' => 'sometimes|string',
            'address_line_2' => 'nullable|string',
            'landmark' => 'nullable|string',
            'city' => 'sometimes|string',
            'state' => 'sometimes|string',
            'country' => 'sometimes|string',
            'postal_code' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user name if provided
        if ($request->has('name')) {
            $user->update(['name' => $request->name]);
        }

        // Update customer profile
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
            'postal_code',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ],
                'profile' => [
                    'phone' => $customer->phone,
                    'whatsapp_contact' => $customer->whatsapp_contact,
                    'full_address' => $customer->full_address,
                    'is_verified' => $customer->is_verified,
                    'verification_level' => $customer->verification_level,
                ]
            ]
        ]);
    }
}
