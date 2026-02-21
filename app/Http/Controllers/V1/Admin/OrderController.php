<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
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
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('full_name', 'LIKE', "%{$search}%");
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
    public function updateOrderStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'order_status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'cancellation_reason' => 'required_if:order_status,cancelled|nullable|string|max:500',
        ]);

        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            if ($request->order_status === 'cancelled') {
                $order->cancel($request->cancellation_reason);
            } else {
                $order->update([
                    'order_status' => $request->order_status
                ]);
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
    public function updatePaymentStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'payment_status' => 'required|in:pending,paid,failed,refunded',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Order not found',
                ], 404);
            }

            if ($request->payment_status === 'paid') {
                $order->markAsPaid($request->payment_reference);
            } else {
                $payment = $order->latestPayment;
                if ($payment) {
                    $payment->update([
                        'payment_status' => $request->payment_status === 'paid' ? 'completed' : $request->payment_status,
                        'payment_reference' => $request->payment_reference,
                    ]);
                } else {
                    $order->payments()->create([
                        'payment_status' => $request->payment_status === 'paid' ? 'completed' : $request->payment_status,
                        'payment_reference' => $request->payment_reference,
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
