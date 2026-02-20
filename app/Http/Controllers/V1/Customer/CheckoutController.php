<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyCouponToCheckoutRequest;
use App\Http\Requests\InitiateCheckoutRequest;
use App\Http\Requests\SetDeliveryAddressRequest;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\CheckoutItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Initiate checkout session (from cart or direct product)
     */
    public function store(InitiateCheckoutRequest $request): JsonResponse
    {
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

            $productId = $request->product_id;

            DB::beginTransaction();
            try {
                // Shared logic: Create checkout session
                $checkoutSession = CheckoutSession::create([
                    'user_id' => $user->id,
                    'subtotal' => 0, // Will be updated
                    'total_amount' => 0, // Will be updated
                    'status' => 'active',
                    'expires_at' => now()->addMinutes(30),
                ]);

                if ($productId) {
                    // FLOW: Buy Now (Single Product)
                    $variantId = $request->variant_id;
                    $quantity = $request->quantity ?? 1;
                    $product = Product::findOrFail($productId);

                    $checkoutSession->update([
                        'type' => 'buy_now',
                    ]);

                    $unitPrice = 0;
                    if ($variantId) {
                        $variant = ProductVariant::where('product_id', $productId)
                            ->where('id', $variantId)
                            ->firstOrFail();
                        $unitPrice = $variant->offer_price ?? $variant->sales_price ?? $variant->price;

                        if ($variant->stock_quantity < $quantity) {
                            throw new \Exception('Insufficient stock available');
                        }
                    } else {
                        if ($product->variants()->count() > 0) {
                            throw new \Exception('Please select a variant for this product');
                        }
                        $unitPrice = $product->offer_price ?? $product->sales_price ?? $product->price ?? 0;
                    }

                    CheckoutItem::create([
                        'checkout_session_id' => $checkoutSession->id,
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $unitPrice * $quantity,
                    ]);
                } else {
                    // FLOW: Cart Checkout
                    $cart = Cart::with(['items.product', 'items.variant'])
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->first();

                    if (!$cart || $cart->items->isEmpty()) {
                        throw new \Exception('Cart is empty');
                    }

                    $checkoutSession->update([
                        'type' => 'cart',
                        'cart_id' => $cart->id,
                    ]);

                    foreach ($cart->items as $cartItem) {
                        CheckoutItem::create([
                            'checkout_session_id' => $checkoutSession->id,
                            'product_id' => $cartItem->product_id,
                            'variant_id' => $cartItem->variant_id,
                            'quantity' => $cartItem->quantity,
                            'unit_price' => $cartItem->unit_price,
                            'total_price' => $cartItem->quantity * $cartItem->unit_price,
                        ]);
                    }
                }

                $checkoutSession->recalculateTotals();
                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Checkout session initiated successfully',
                    'data' => $checkoutSession->load(['items.product', 'items.variant']),
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initiate checkout',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get checkout session
     */
    public function show($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $checkoutSession = CheckoutSession::with([
                'items.product',
                'items.variant',
                'coupon',
                'deliveryAddress'
            ])->find($id);

            if (!$checkoutSession) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout session not found'
                ], 404);
            }

            // Verify ownership
            if ($checkoutSession->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to checkout session'
                ], 403);
            }

            // Check if expired
            if ($checkoutSession->isExpired()) {
                $checkoutSession->markAsExpired();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout session has expired'
                ], 410);
            }

            return response()->json([
                'status' => 'success',
                'data' => $checkoutSession,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checkout session not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Apply coupon to checkout session
     */
    public function applyCoupon($id, ApplyCouponToCheckoutRequest $request): JsonResponse
    {
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

            $couponCode = $request->code;

            $checkoutSession = CheckoutSession::findOrFail($id);

            // Verify ownership
            if ($checkoutSession->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to checkout session'
                ], 403);
            }

            // Verify session is active
            if (!$checkoutSession->isActive()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout session has expired'
                ], 422);
            }

            // Find and validate coupon
            $coupon = Coupon::with('tiers')
                ->active()
                ->where('code', $couponCode)
                ->first();

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The coupon code is invalid or has expired'
                ], 422);
            }

            // Validate coupon for user
            $validation = $coupon->isValidForUser($user->id);
            if (!$validation['status']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validation['message']
                ], 422);
            }

            // Check minimum purchase amount
            if ($coupon->min_purchase_amount && $checkoutSession->subtotal < $coupon->min_purchase_amount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Minimum purchase amount of ' . $coupon->min_purchase_amount . ' is required for this coupon'
                ], 422);
            }

            // Apply coupon
            $checkoutSession->applyCoupon($coupon);

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon applied successfully',
                'data' => $checkoutSession->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to apply coupon',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove coupon from checkout session
     */
    public function removeCoupon($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $checkoutSession = CheckoutSession::findOrFail($id);

            // Verify ownership
            if ($checkoutSession->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to checkout session'
                ], 403);
            }

            $checkoutSession->removeCoupon();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon removed successfully',
                'data' => $checkoutSession->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove coupon',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Set delivery address
     */
    public function setDeliveryAddress($id, SetDeliveryAddressRequest $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can access Set Delivery Address'
                ], 403);
            }

            $checkoutSession = CheckoutSession::findOrFail($id);

            // Verify ownership
            if ($checkoutSession->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to checkout session'
                ], 403);
            }

            // Verify session is active
            if (!$checkoutSession->isActive()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Checkout session has expired'
                ], 422);
            }

            $checkoutSession->update(['delivery_address_id' => $request->delivery_address_id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery address set successfully',
                'data' => $checkoutSession->fresh(['deliveryAddress']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set delivery address',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
