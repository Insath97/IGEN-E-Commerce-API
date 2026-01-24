<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Get active cart for current user
     */
    public function getCart()
    {
        try {
            $user = auth('api')->user();

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can access cart'
                ], 403);
            }

            if (!$user->activeCart) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Cart is empty',
                    'data' => null
                ], 200);
            }

            $cart = Cart::with([
                'items.product' => function ($query) {
                    $query->select('id', 'name', 'slug', 'primary_image_path', 'type', 'short_description');
                },
                'items.variant' => function ($query) {
                    $query->select('id', 'product_id', 'variant_name', 'sku', 'price', 'sales_price', 'offer_price', 'color', 'storage_size', 'ram_size', 'condition');
                }
            ])
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Cart is empty',
                    'data' => null
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Cart retrieved successfully',
                'data' => $cart
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cart',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Add product to cart
     */
    public function addToCart(AddToCartRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = auth('api')->user();

            $data = $request->validated();

            // Find or create active cart
            $cart = $this->getOrCreateActiveCart($user->id);

            // Get product and variant to check price
            $product = Product::findOrFail($data['product_id']);
            $unitPrice = 0;

            if (!empty($data['variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('id', $data['variant_id'])
                    ->firstOrFail();
                $unitPrice = $variant->offer_price ?? $variant->sales_price ?? $variant->price;
            } else {
                if ($product->variants()->count() > 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Please select a variant for this product',
                    ], 422);
                }
                $unitPrice = 0;
            }

            // Check stock availability
            if (!$this->checkStockAvailability($product, $variant, $data['quantity'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock available',
                ], 422);
            }

            // Check if item already exists in cart
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $data['product_id'])
                ->where('variant_id', $data['variant_id'] ?? null)
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $data['quantity'];
                $cartItem->unit_price = $unitPrice;
                $cartItem->save();
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $data['product_id'],
                    'variant_id' => $data['variant_id'] ?? null,
                    'quantity' => $data['quantity'],
                    'unit_price' => $unitPrice,
                ]);
            }

            DB::commit();

            $cartResult = $cart->fresh([
                'items.product' => function ($query) {
                    $query->select('id', 'name', 'slug', 'primary_image_path', 'type', 'short_description');
                },
                'items.variant' => function ($query) {
                    $query->select('id', 'product_id', 'variant_name', 'sku', 'price', 'sales_price', 'offer_price', 'color', 'storage_size', 'ram_size', 'condition');
                }
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Item added to cart successfully',
                'data' => $cartResult
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add item to cart',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItem(UpdateCartRequest $request, $itemId)
    {
        try {
            $user = auth('api')->user();

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can access cart'
                ], 403);
            }

            $cart = $this->getOrCreateActiveCart($user->id);
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('id', $itemId)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cart item not found or does not belong to your cart',
                    'cart_id' => $cart->id,
                    'item_id' => $itemId
                ], 404);
            }

            // Check stock availability for new quantity
            if (!$this->checkStockAvailability(
                $cartItem->product,
                $cartItem->variant,
                $request->quantity
            )) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient stock available',
                ], 422);
            }
            $cartItem->update(['quantity' => $request->quantity]);

            $cartResult = $cart->fresh([
                'items.product' => function ($query) {
                    $query->select('id', 'name', 'slug', 'primary_image_path', 'type', 'short_description');
                },
                'items.variant' => function ($query) {
                    $query->select('id', 'product_id', 'variant_name', 'sku', 'price', 'sales_price', 'offer_price', 'color', 'storage_size', 'ram_size', 'condition');
                }
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cart updated successfully',
                'data' => $cartResult
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update cart',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function remove($itemId)
    {
        try {
            $user = auth('api')->user();

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can access cart'
                ], 403);
            }

            $cart = Cart::where('user_id', $user->id)->where('status', 'active')->first();

            if (!$cart) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cart not found'
                ], 404);
            }

            $cart = $this->getOrCreateActiveCart($user->id);
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('id', $itemId)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cart item not found or does not belong to your cart'
                ], 404);
            }

            $cartItem->delete();


            $cartResult = $cart->fresh([
                'items.product' => function ($query) {
                    $query->select('id', 'name', 'slug', 'primary_image_path', 'type', 'short_description');
                },
                'items.variant' => function ($query) {
                    $query->select('id', 'product_id', 'variant_name', 'sku', 'price', 'sales_price', 'offer_price', 'color', 'storage_size', 'ram_size', 'condition');
                }
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Item removed from cart successfully',
                'data' => $cartResult
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove item',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Clear active cart
     */
    public function clearCart()
    {
        try {
            $user = auth('api')->user();

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can access cart'
                ], 403);
            }

            $cart = Cart::where('user_id', $user->id)->where('status', 'active')->first();

            if (!$cart) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Cart is already empty'
                ], 200);
            }

            $cart = $this->getOrCreateActiveCart($user->id);
            $cart->items()->delete();
            $cart->recalculateTotals();

            return response()->json([
                'status' => 'success',
                'message' => 'Cart cleared successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear cart',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function mergeWithUserCart()
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

            $sessionId = session()->getId();
            $guestCart = Cart::where('session_id', $sessionId)
                ->where('status', 'active')
                ->whereNull('user_id')
                ->first();

            if (!$guestCart || $guestCart->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No guest cart to merge',
                    'merged' => false
                ], 200);
            }

            $userCart = $this->getOrCreateActiveCart($user->id);
            $userCart->mergeCart($guestCart);

            $cartResult = $userCart->fresh([
                'items.product' => function ($query) {
                    $query->select('id', 'name', 'slug', 'primary_image_path', 'type', 'short_description');
                },
                'items.variant' => function ($query) {
                    $query->select('id', 'product_id', 'variant_name', 'sku', 'price', 'sales_price', 'offer_price', 'color', 'storage_size', 'ram_size', 'condition');
                }
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Carts merged successfully',
                'merged' => true,
                'data' => $cartResult
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to merge cart',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get or create active cart
     */
    private function getOrCreateActiveCart($userId): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $userId, 'status' => 'active'],
            [
                'total_amount' => 0,
                'item_count' => 0,
                'session_id' => session()->getId()
            ]
        );
    }

    /**
     * Helper: Check stock availability
     */
    private function checkStockAvailability(Product $product, ?ProductVariant $variant, int $quantity): bool
    {
        if ($variant) {
            return $variant->stock_quantity >= $quantity;
        }

        return $product->stock_quantity >= $quantity;
    }
}
