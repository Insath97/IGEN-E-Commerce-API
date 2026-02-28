<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCustomerRequest;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Customer Index', only: ['index','show']),
            new Middleware('permission:Customer Activate', only: ['activate']),
            new Middleware('permission:Customer Deactivate', only: ['deactivate']),
            new Middleware('permission:Customer Verify', only: ['verify']),
            new Middleware('permission:Customer Delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the customers.
     */
    public function index(ListCustomerRequest $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $orderBy = $request->get('order_by', 'created_at');
            $orderDirection = $request->get('order_direction', 'desc');

            $query = Customer::with(['user' => function ($q) {
                $q->select('id', 'name', 'username', 'email', 'profile_image', 'is_active', 'last_login_at');
            }]);

            // Search
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('username', 'LIKE', "%{$search}%");
                })->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('whatsapp_number', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            }

            // Status Filters
            if ($request->has('is_active')) {
                $status = $request->boolean('is_active');
                $query->whereHas('user', function ($q) use ($status) {
                    $q->where('is_active', $status);
                });
            }

            if ($request->has('is_verified')) {
                $query->where('is_verified', $request->boolean('is_verified'));
            }

            // Ordering
            if (in_array($orderBy, ['id', 'created_at'])) {
                $query->orderBy($orderBy, $orderDirection);
            } else {
                // Sorting by user fields requires a join
                $query->join('users', 'customers.user_id', '=', 'users.id')
                    ->select('customers.*')
                    ->orderBy("users.{$orderBy}", $orderDirection);
            }

            $customers = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Customers retrieved successfully',
                'data' => $customers
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id)
    {
        try {
            $customer = Customer::with(['user'])->find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Customer retrieved successfully',
                'data' => $customer
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customer',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Activate customer user account
     */
    public function activate(string $id)
    {
        try {
            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            $user = $customer->user;
            if ($user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer account is already active'
                ], 422);
            }

            $user->update(['is_active' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer account activated successfully',
                'data' => $customer->load('user')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate customer',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate customer user account
     */
    public function deactivate(string $id)
    {
        try {
            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            $user = $customer->user;
            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer account is already inactive'
                ], 422);
            }

            $user->update(['is_active' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer account deactivated successfully',
                'data' => $customer->load('user')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate customer',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Manually verify a customer
     */
    public function verify(string $id)
    {
        try {
            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            if ($customer->is_verified) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer is already verified'
                ], 422);
            }

            $customer->markAsVerified();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer verified successfully',
                'data' => $customer->load('user')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify customer',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer and associated user
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found'
                ], 404);
            }

            $user = $customer->user;

            // Delete customer and user
            $customer->delete();
            $user->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer and associated user deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete customer',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
