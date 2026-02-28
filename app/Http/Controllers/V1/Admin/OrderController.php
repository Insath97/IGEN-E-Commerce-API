<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Requests\UpdatePaymentStatusRequest;
use App\Http\Requests\VerifyOrderRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerOrderCancelledMail;
use App\Mail\CustomerOrderShippedMail;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Order Index', only: ['index', 'getCustomerOrders','show']),
            new Middleware('permission:Order Statistics', only: ['statistics']),
            new Middleware('permission:Order Verify', only: ['verify']),
            new Middleware('permission:Order Status Update', only: ['updateOrderStatus']),
            new Middleware('permission:Order Payment Update', only: ['updatePaymentStatus']),
        ];
    }
    /**
     * Get all orders with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Order::with(['customer', 'user', 'items.product', 'items.variant', 'deliveryAddress', 'coupon', 'latestPayment']);

            // Search by order number or customer name
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer.user', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Filter by order status
            if ($request->has('order_status')) {
                $query->where('order_status', $request->order_status);
            }

            // Filter by payment status
            if ($request->has('payment_status')) {
                $status = $request->payment_status === 'paid' ? 'completed' : $request->payment_status;
                $query->whereHas('payments', function ($q) use ($status) {
                    $q->where('payment_status', $status);
                });
            }

            // Filter by payment method
            if ($request->has('payment_method')) {
                $method = $request->payment_method;
                $query->whereHas('payments', function ($q) use ($method) {
                    $q->where('payment_method', $method);
                });
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Ordering
            $query->orderBy('created_at', 'desc');

            $orders = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Orders retrieved successfully',
                'data' => $orders
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve orders',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order with all details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $order = Order::with([
                'customer',
                'user',
                'items.product',
                'items.variant',
                'deliveryAddress',
                'coupon.tiers',
                'checkoutSession',
                'payments'
            ])->find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order retrieved successfully',
                'data' => $order
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            if ($data['order_status'] === 'cancelled') {
                $order->cancel($data['cancellation_reason']);
            } elseif ($data['order_status'] === 'shipped') {
                $order->markAsShipped([
                    'courier_name' => $data['courier_name'],
                    'courier_phone' => $data['courier_phone'] ?? null,
                    'tracking_number' => $data['tracking_number'],
                    'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
                    'shipping_notes' => $data['shipping_notes'] ?? null,
                ]);
            } else {
                $order->update([
                    'order_status' => $data['order_status']
                ]);
            }

            // Customer Notification Logic
            try {
                $isCustomerNotificationEnabled = Setting::getValue('customer_order_notification_enabled', '1') == '1';
                if ($isCustomerNotificationEnabled && $order->user && $order->user->email) {
                    if ($data['order_status'] === 'cancelled') {
                        Mail::to($order->user->email)->send(new CustomerOrderCancelledMail($order));
                        Log::info("Cancellation email sent to customer: " . $order->user->email . " for order #" . $order->order_number);
                    } elseif ($data['order_status'] === 'shipped') {
                        Mail::to($order->user->email)->send(new CustomerOrderShippedMail($order));
                        Log::info("Shipping email sent to customer: " . $order->user->email . " for order #" . $order->order_number);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to send customer order notification: " . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated successfully',
                'data' => $order->fresh()
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(UpdatePaymentStatusRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();

        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            if ($data['payment_status'] === 'paid') {
                $order->markAsPaid($data['payment_reference'] ?? null);
            } else {
                $payment = $order->latestPayment;
                if ($payment) {
                    $payment->update([
                        'payment_status' => $data['payment_status'] === 'paid' ? 'completed' : $data['payment_status'],
                        'payment_reference' => $data['payment_reference'] ?? null,
                    ]);
                } else {
                    $order->payments()->create([
                        'payment_status' => $data['payment_status'] === 'paid' ? 'completed' : $data['payment_status'],
                        'payment_reference' => $data['payment_reference'] ?? null,
                        'amount' => $order->total_amount,
                        'payment_method' => 'other', // Default fallback
                        'currency' => 'LKR',
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment status updated successfully',
                'data' => $order->fresh()
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Verify and process order based on payment method
     */
    public function verify(VerifyOrderRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        try {
            $order = Order::with('latestPayment')->find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            $payment = $order->latestPayment;
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No payment record found for this order',
                ], 422);
            }

            $adminId = auth('api')->id();
            $notes = $data['notes'] ?? null;

            // Logic based on payment method
            switch ($payment->payment_method) {
                case 'bank_transfer':
                    $order->confirmPayment($adminId, $notes);
                    $message = 'Bank transfer verified. Order moved to processing and payment marked as completed.';
                    break;

                case 'cash_on_delivery':
                    $order->markAsProcessing();
                    $message = 'COD order moved to processing. Payment status remains pending.';
                    break;

                case 'card':
                    // Future logic for manual override if needed
                    $order->markAsProcessing();
                    $message = 'Card payment order moved to processing.';
                    break;

                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unsupported payment method for verification flow',
                    ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $order->fresh(['latestPayment'])
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify order',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get order statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_orders' => Order::count(),
                'pending_orders' => Order::pending()->count(),
                'processing_orders' => Order::processing()->count(),
                'shipped_orders' => Order::shipped()->count(),
                'delivered_orders' => Order::delivered()->count(),
                'cancelled_orders' => Order::cancelled()->count(),
                'total_revenue' => Order::paid()->sum('total_amount'),
                'pending_payments' => Order::unpaid()->sum('total_amount'),
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Order statistics retrieved successfully',
                'data' => $stats
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve order statistics',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer's order history
     */
    public function getCustomerOrders(string $customerId): JsonResponse
    {
        try {
            $orders = Order::with(['items.product', 'items.variant', 'deliveryAddress', 'coupon'])
                ->where('customer_id', $customerId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer orders retrieved successfully',
                'data' => $orders
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customer orders',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
