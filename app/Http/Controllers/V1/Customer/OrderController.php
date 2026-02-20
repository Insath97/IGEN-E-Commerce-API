<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\ConfirmCheckoutRequest;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use FileUploadTrait;

    /**
     * Get all orders for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $status = $request->query('status');
            $perPage = $request->get('per_page', 15);

            $query = Order::with(['items.product', 'items.variant', 'deliveryAddress', 'latestPayment'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('order_status', $status);
            }

            $orders = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Orders retrieved successfully',
                'data' => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single order
     */
    public function show($id): JsonResponse
    {
        try {
            $user = auth('api')->user();

            $order = Order::with([
                'items.product',
                'items.variant',
                'deliveryAddress',
                'coupon',
                'payments'
            ])->findOrFail($id);

            // Verify ownership
            if ($order->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => $order,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Confirm checkout and create order
     */
    public function confirmCheckout($id, ConfirmCheckoutRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = auth('api')->user();
            $customer = $user->customer;

            $data = $request->validated();

            if ($user->user_type != 'customer') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only customers can access Set Delivery Address'
                ], 403);
            }

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer profile not found',
                ], 403);
            }

            $checkoutSession = CheckoutSession::with([
                'items.product',
                'items.variant',
                'coupon',
                'deliveryAddress'
            ])->findOrFail($id);

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
                    'message' => 'Checkout session has expired or already completed'
                ], 422);
            }

            // Verify delivery address is set
            if (!$checkoutSession->delivery_address_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please select a delivery address'
                ], 422);
            }

            // Generate sequential order number: IGEN-YYMM0001
            $yearMonth = now()->format('ym');
            $lastOrder = Order::where('order_number', 'LIKE', "IGEN-{$yearMonth}%")
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            if ($lastOrder) {
                $lastSequence = (int) substr($lastOrder->order_number, -4);
                $sequence = $lastSequence + 1;
            }
            $orderNumber = 'IGEN-' . $yearMonth . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            // Handle payment slip upload
            $paymentSlipPath = $this->handleFileUpload($request, 'payment_slip', null, 'order/paymentslip', $orderNumber);

            // Create order
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'customer_id' => $customer->id,
                'checkout_session_id' => $checkoutSession->id,
                'subtotal' => $checkoutSession->subtotal,
                'discount_amount' => $checkoutSession->discount_amount,
                'tax_amount' => $checkoutSession->tax_amount,
                'shipping_fee' => $checkoutSession->shipping_fee,
                'total_amount' => $checkoutSession->total_amount,
                'coupon_id' => $checkoutSession->coupon_id,
                'coupon_code' => $checkoutSession->coupon_code,
                'delivery_address_id' => $checkoutSession->delivery_address_id,
                'order_status' => 'pending',
            ]);

            // Create payment
            $payment = Payment::create([
                'order_id' => $order->id,
                'checkout_session_id' => $checkoutSession->id,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'amount' => $order->total_amount,
                'slip_path' => $paymentSlipPath,
                'currency' => 'LKR', // Defaulting based on migration
                'ip_address' => $request->ip(),
            ]);

            // Create order items from checkout items
            foreach ($checkoutSession->items as $checkoutItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $checkoutItem->product_id,
                    'variant_id' => $checkoutItem->variant_id,
                    'product_name' => $checkoutItem->product->name,
                    'variant_name' => $checkoutItem->variant->variant_name ?? null,
                    'sku' => $checkoutItem->variant->sku ?? null,
                    'quantity' => $checkoutItem->quantity,
                    'unit_price' => $checkoutItem->unit_price,
                    'total_price' => $checkoutItem->total_price,
                ]);

                // Reduce stock
                if ($checkoutItem->variant) {
                    $checkoutItem->variant->decrement('stock_quantity', $checkoutItem->quantity);
                }
            }

            // Track coupon usage
            if ($checkoutSession->coupon_id) {
                CouponUsage::create([
                    'coupon_id' => $checkoutSession->coupon_id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ]);

                // Increment coupon used count
                Coupon::where('id', $checkoutSession->coupon_id)->increment('used_count');
            }

            // Clear cart if cart-based checkout
            if ($checkoutSession->type === 'cart' && $checkoutSession->cart_id) {
                $checkoutSession->cart->items()->delete();
                $checkoutSession->cart->update(['status' => 'completed']);
            }

            // Mark checkout session as completed
            $checkoutSession->markAsCompleted();

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => $order->load(['items', 'deliveryAddress', 'coupon', 'latestPayment']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel order
     */
    public function cancel($id, CancelOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = auth('api')->user();

            $data = $request->validated();

            $order = Order::findOrFail($id);

            // Verify ownership
            if ($order->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            // Check if order can be cancelled (within 3 days and not shipped)
            if (!$order->canBeCancelled()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order cannot be cancelled. Cancellation is only allowed within ' . Order::CANCELLATION_LIMIT_DAYS . ' days of purchase and before the order is shipped.'
                ], 422);
            }

            // Restore stock
            foreach ($order->items as $item) {
                if ($item->variant) {
                    $item->variant->increment('stock_quantity', $item->quantity);
                }
            }

            // Cancel order
            $order->cancel($data['reason']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order cancelled successfully',
                'data' => $order->fresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
