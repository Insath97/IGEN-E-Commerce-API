<?php

namespace App\Http\Controllers\V1\Frondend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /* featured brand listing */
    public function featuredBrands(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);

            $brands = Brand::active()
                ->featured()
                ->ordered()
                ->limit($limit)
                ->get()->map->only(['id', 'name', 'logo']);

            if ($brands->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No featured brands found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Featured brands retrieved successfully',
                'data' => $brands
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve featured brands',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getCategories()
    {
        try {
            $catecoreies = Category::active()
                ->ordered()
                ->get()->map->only(['id', 'name', 'slug']);

            if ($catecoreies->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No categories found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Categories retrieved successfully',
                'data' => $catecoreies
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories',
                'error' => $th->getMessage()
            ], 500);
        }
    }


    public function productsGetAll(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Product::with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants',
                'features:id,name',
                'specifications:id,product_id,specification_name,specification_value',
                'tags:id,name,slug',
                'compatibleProducts:id,name,primary_image_path',
                'bundledProducts:id,name,primary_image_path',
            ])->active();

            // Search
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->search($search);
            }

            // Filters
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->boolean('is_featured'));
            }

            if ($request->has('is_trending')) {
                $query->where('is_trending', $request->boolean('is_trending'));
            }

            // Ordering
            if ($request->has('order_by')) {
                $orderBy = $request->order_by;
                $direction = $request->get('order_direction', 'asc');
                $query->orderBy($orderBy, $direction);
            } else {
                $query->ordered();
            }

            $products = $query->paginate($perPage);

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No products found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Products retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve products',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function ProductById(string $id)
    {
        try {
            $product = Product::with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants',
                'features:id,name',
                'specifications:id,product_id,specification_name,specification_value',
                'tags:id,name,slug',
                'compatibleProducts:id,name,primary_image_path',
                'bundledProducts:id,name,primary_image_path',
            ])->active()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product retrieved successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve product',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
