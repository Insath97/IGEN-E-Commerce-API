<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Customer;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard summary statistics
     */
    public function index(): JsonResponse
    {
        try {
            // Basic Metrics
            $totalOrders = Order::count();
            $totalRevenue = (float) Order::paid()->sum('total_amount');
            $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            
            $totalCustomers = User::where('user_type', 'customer')->count();
            $newCustomersCount = User::where('user_type', 'customer')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            // Growth Rate Calculation (Month over Month)
            $currentMonthRevenue = (float) Order::paid()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total_amount');

            $lastMonthRevenue = (float) Order::paid()
                ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                ->sum('total_amount');

            $revenueGrowthRate = 0;
            if ($lastMonthRevenue > 0) {
                $revenueGrowthRate = (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100;
            } elseif ($currentMonthRevenue > 0) {
                $revenueGrowthRate = 100;
            }

            // Inventory & Products
            $lowStockCount = ProductVariant::where('stock_quantity', '<=', DB::raw('low_stock_threshold'))
                ->orWhere('stock_quantity', '<', 10)
                ->count();

            $topSellingProducts = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(order_items.quantity * order_items.unit_price) as revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get();

            // Status Distribution
            $statusDistribution = Order::select('order_status', DB::raw('count(*) as count'))
                ->groupBy('order_status')
                ->get()
                ->pluck('count', 'order_status');

            return response()->json([
                'status' => 'success',
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'revenue' => [
                        'total' => $totalRevenue,
                        'average_order_value' => round($averageOrderValue, 2),
                        'growth_rate' => round($revenueGrowthRate, 2),
                        'current_month' => $currentMonthRevenue,
                    ],
                    'orders' => [
                        'total' => $totalOrders,
                        'status_distribution' => $statusDistribution,
                    ],
                    'customers' => [
                        'total' => $totalCustomers,
                        'new_30_days' => $newCustomersCount,
                    ],
                    'inventory' => [
                        'low_stock_count' => $lowStockCount,
                    ],
                    'top_selling_products' => $topSellingProducts,
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard statistics',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get revenue analytics for charts (Week, Month, Year)
     */
    public function revenueAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->query('period', 'month'); // week, month, year

            $query = Order::paid();

            if ($period === 'week') {
                $startDate = now()->startOfWeek();
                $data = $query->where('created_at', '>=', $startDate)
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('SUM(total_amount) as total')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
            } elseif ($period === 'year') {
                $startDate = now()->startOfYear();
                $data = $query->where('created_at', '>=', $startDate)
                    ->select(
                        DB::raw('MONTHNAME(created_at) as month'),
                        DB::raw('SUM(total_amount) as total')
                    )
                    ->groupBy('month')
                    ->orderBy(DB::raw('MIN(created_at)'))
                    ->get();
            } else {
                // Default: current month by day
                $startDate = now()->startOfMonth();
                $data = $query->where('created_at', '>=', $startDate)
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('SUM(total_amount) as total')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Revenue analytics retrieved successfully',
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve revenue analytics',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get recent 5 orders
     */
    public function recentOrders(): JsonResponse
    {
        try {
            $recentOrders = Order::with(['user:id,name', 'customer:id,user_id'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($order) {
                    return [
                        'order_number' => $order->order_number,
                        'customer_name' => $order->user ? $order->user->name : 'Unknown',
                        'amount' => (float) $order->total_amount,
                        'status' => $order->order_status,
                        'date' => $order->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'status' => 'success',
                'message' => 'Recent orders retrieved successfully',
                'data' => $recentOrders
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve recent orders',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
