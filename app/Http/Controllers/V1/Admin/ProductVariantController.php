<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $query = ProductVariant::with(['product:id,name,slug', 'creator:id,name']);

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('variant_name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->boolean('is_featured'));
            }

            $variants = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Product variants retrieved successfully',
                'data' => $variants
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product variants',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function store(CreateProductVariantRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth('api')->id();

            $variant = ProductVariant::create($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant created successfully',
                'data' => $variant->load('product:id,name')
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $variant = ProductVariant::with(['product', 'creator:id,name'])->find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant retrieved successfully',
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(UpdateProductVariantRequest $request, $id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant updated successfully',
                'data' => $variant->load('product:id,name')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $variant = ProductVariant::withTrashed()->find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant restored successfully',
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function forceDestroy($id)
    {
        try {
            $variant = ProductVariant::withTrashed()->find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant permanently deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to permanently delete product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activate($id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            if ($variant->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant is already active'
                ], 422);
            }

            $variant->activate();

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant activated successfully',
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function deactivate($id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            if (!$variant->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant is already inactive'
                ], 422);
            }

            $variant->deactivate();

            return response()->json([
                'status' => 'success',
                'message' => 'Product variant deactivated successfully',
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate product variant',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->update([
                'is_active' => !$variant->is_active
            ]);

            $status = $variant->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'status' => 'success',
                'message' => "Product variant {$status} successfully",
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle active status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleFeatured($id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->toggleFeatured();

            $status = $variant->is_featured ? 'featured' : 'unfeatured';

            return response()->json([
                'status' => 'success',
                'message' => "Product variant {$status} successfully",
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle featured status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleTrending($id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found'
                ], 404);
            }

            $variant->toggleTrending();

            $status = $variant->is_trending ? 'trending' : 'normal';

            return response()->json([
                'status' => 'success',
                'message' => "Product variant set to {$status} successfully",
                'data' => $variant
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle trending status',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
