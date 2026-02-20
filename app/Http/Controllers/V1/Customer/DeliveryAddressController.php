<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateDeliveryAddressRequest;
use App\Http\Requests\UpdateDeliveryAddressRequest;
use App\Models\DeliveryAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DeliveryAddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $customer = auth('api')->user()->customer;
            $addresses = $customer->deliveryAddresses;

            if ($addresses->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No addressess found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery addresses retrieved successfully',
                'data' => $addresses
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateDeliveryAddressRequest $request): JsonResponse
    {
        $customer = auth('api')->user()->customer;

        // Check for max 2 addresses constraint
        if ($customer->deliveryAddresses()->count() >= 2) {
            return response()->json([
                'success' => false,
                'message' => 'You can only store a maximum of 2 delivery addresses.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['customer_id'] = $customer->id;

            if (!empty($data['is_default'])) {
                $customer->deliveryAddresses()->update(['is_default' => false]);
            }

            $address = DeliveryAddress::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery address created successfully.',
                'data' => $address
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create delivery address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $customer = auth('api')->user()->customer;
        $address = $customer->deliveryAddresses()->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery address not found.'
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Address retrieved successfully',
            'data' => $address

        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeliveryAddressRequest $request, $id): JsonResponse
    {
        $customer = auth('api')->user()->customer;
        $address = $customer->deliveryAddresses()->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery address not found.'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();

            if (!empty($data['is_default']) && !$address->is_default) {
                $customer->deliveryAddresses()->update(['is_default' => false]);
            }

            $address->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Delivery address updated successfully.',
                'data' => $address
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $customer = auth('api')->user()->customer;
        $address = $customer->deliveryAddresses()->find($id);

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery address not found.'
            ], 404);
        }

        try {
            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Delivery address deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete delivery address.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
