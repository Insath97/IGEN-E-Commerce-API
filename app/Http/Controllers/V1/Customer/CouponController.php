<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    /**
     * Validate and calculate discount for a coupon
     */
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|exists:coupons,code',
            'amount' => 'required|numeric|min:0',
        ]);

        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can have carts'
                ], 403);
            }

            $couponCode = trim($request->input('code'));
            $totalAmount = $request->input('amount');

            // Find the coupon
            $coupon = Coupon::with('tiers')->active()
                ->where('code', $couponCode)
                ->first();

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The coupon code is invalid or has expired.'
                ], 422);
            }

            // 1. Validate coupon (Expiry, Usage Limits, Per-User Limit)
            $validation = $coupon->isValidForUser($user->id);
            if (!$validation['status']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validation['message']
                ], 422);
            }

            // 2. Check Minimum Purchase Amount
            if ($coupon->min_purchase_amount && $totalAmount < $coupon->min_purchase_amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Minimum purchase amount of ' . $coupon->min_purchase_amount . ' is required for this coupon.'
                ], 422);
            }

            // 3. Calculate Discount
            $discountAmount = $coupon->calculateDiscount($totalAmount);

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon validated successfully.',
                'data' => [
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                    'type' => $coupon->type,
                    'subtotal' => round($totalAmount, 2),
                    'discount_amount' => $discountAmount,
                    'final_amount' => round($totalAmount - $discountAmount, 2),
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process coupon.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
