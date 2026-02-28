<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CouponController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Coupon Index', only: ['index', 'show']),
            new Middleware('permission:Coupon Create', only: ['store']),
            new Middleware('permission:Coupon Update', only: ['update']),
            new Middleware('permission:Coupon Delete', only: ['destroy']),
            new Middleware('permission:Coupon Activate', only: ['activate']),
            new Middleware('permission:Coupon Deactivate', only: ['deactivate']),
            new Middleware('permission:Coupon Toggle', only: ['toggleActive']),
            new Middleware('permission:Coupon Usage', only: ['getAllCouponUsages', 'getCouponUsage']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = Coupon::query()->with('tiers');

            // Search
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            // Filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Ordering
            $query->ordered();

            $coupons = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Coupons retrieved successfully',
                'data' => $coupons
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve coupons',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function store(CreateCouponRequest $request)
    {
        try {
            $data = $request->validated();

            // Auto-generate code if not provided
            if (empty($data['code'])) {
                $data['code'] = strtoupper(Str::random(8));
                // Ensure unique code
                while (Coupon::where('code', $data['code'])->exists()) {
                    $data['code'] = strtoupper(Str::random(8));
                }
            } else {
                $data['code'] = strtoupper($data['code']);
            }

            $coupon = Coupon::create($data);

            if ($data['type'] === 'tiered_percentage' && isset($data['tiers'])) {
                foreach ($data['tiers'] as $index => $tier) {
                    $tier['priority'] = $index + 1;
                    $coupon->tiers()->create($tier);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon created successfully',
                'data' => $coupon->load('tiers')
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create coupon',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $coupon = Coupon::with('tiers')->find($id);

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon retrieved successfully',
                'data' => $coupon
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve coupon',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(UpdateCouponRequest $request, string $id)
    {
        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon not found',
                    'data' => []
                ], 404);
            }

            $data = $request->validated();

            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            $coupon->update($data);

            if (isset($data['tiers'])) {
                // Remove tiers not in the request
                $tierIds = collect($data['tiers'])->pluck('id')->filter()->toArray();
                $coupon->tiers()->whereNotIn('id', $tierIds)->delete();

                foreach ($data['tiers'] as $index => $tierData) {
                    $tierData['priority'] = $index + 1;
                    if (isset($tierData['id'])) {
                        $coupon->tiers()->where('id', $tierData['id'])->update($tierData);
                    } else {
                        $coupon->tiers()->create($tierData);
                    }
                }
            }

            $coupon->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon updated successfully',
                'data' => $coupon->load('tiers')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update coupon',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon not found',
                    'data' => []
                ], 404);
            }

            $coupon->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete coupon',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activate(string $id)
    {
        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon not found',
                    'data' => []
                ], 404);
            }

            if ($coupon->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon is already active',
                ], 422);
            }

            $coupon->activate();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon activated successfully',
                'data' => $coupon
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate coupon',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon not found',
                    'data' => []
                ], 404);
            }

            if (!$coupon->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon is already inactive',
                ], 422);
            }

            $coupon->deactivate();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon deactivated successfully',
                'data' => $coupon
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate coupon',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleActive(string $id)
    {
        try {
            $coupon = Coupon::find($id);

            if (!$coupon) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Coupon not found',
                    'data' => []
                ], 404);
            }

            $coupon->update(['is_active' => !$coupon->is_active]);

            $status = $coupon->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'status' => 'success',
                'message' => "Coupon {$status} successfully",
                'data' => $coupon
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle coupon status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get all coupon usages with filters
     */
    public function getAllCouponUsages(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = CouponUsage::with(['user.customer', 'coupon', 'order']);

            // Filter by customer
            if ($request->has('customer_id')) {
                $query->whereHas('user.customer', function ($q) use ($request) {
                    $q->where('id', $request->customer_id);
                });
            }

            // Filter by coupon
            if ($request->has('coupon_id')) {
                $query->where('coupon_id', $request->coupon_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $query->orderBy('created_at', 'desc');

            $usages = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon usages retrieved successfully',
                'data' => $usages
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve coupon usages',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get coupon usage for a specific coupon
     */
    public function getCouponUsage(string $couponId)
    {
        try {
            $usages = CouponUsage::with(['user.customer', 'order'])
                ->where('coupon_id', $couponId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Coupon usage retrieved successfully',
                'data' => $usages
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve coupon usage',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
