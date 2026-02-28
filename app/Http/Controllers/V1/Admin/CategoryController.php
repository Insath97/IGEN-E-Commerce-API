<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CeateCategoryrequest;
use App\Http\Requests\UpdateCategoryrequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Category Index', only: ['index', 'show']),
            new Middleware('permission:Category List', only: ['activeList']),
            new Middleware('permission:Category Create', only: ['store']),
            new Middleware('permission:Category Update', only: ['update']),
            new Middleware('permission:Category Delete', only: ['destroy']),
            new Middleware('permission:Category Force Delete', only: ['forceDestroy']),
            new Middleware('permission:Category Restore', only: ['restore']),
            new Middleware('permission:Category Activate', only: ['activate']),
            new Middleware('permission:Category Deactivate', only: ['deactivate']),
            new Middleware('permission:Category Featured', only: ['toggleFeatured']),
        ];
    }
    
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Category::with('creator:id,name,email');

            // Search
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->search($search);
            }

            // Filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->boolean('is_featured'));
            }

            // Ordering
            if ($request->has('order_by')) {
                $orderBy = $request->order_by;
                $direction = $request->get('order_direction', 'asc');
                $query->orderBy($orderBy, $direction);
            } else {
                $query->ordered();
            }

            $categories = $query->paginate($perPage);

            if ($categories->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No categories found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Categories retrieved successfully',
                'data' => $categories
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function create() {}

    public function store(CeateCategoryrequest $request)
    {
        try {
            $currentUser = auth('api')->user();
            $data = $request->validated();

            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $data['created_by'] = $currentUser->id;

            $category = Category::create($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $category = Category::with('creator:id,name,email')->find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Category retrieved successfully',
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function edit(string $id) {}

    public function update(UpdateCategoryrequest  $request, string $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            $data = $request->validated();

            if (isset($data['name']) && $data['name'] !== $category->name) {
                $data['slug'] = Str::slug($data['name']);
            }

            $category->update($data);
            $category->refresh();
            $category->load('creator:id,name,email');

            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully',
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function forceDestroy(string $id)
    {
        try {
            $category = Category::withTrashed()->find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            $category->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category permanently deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to permanently delete category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $category = Category::withTrashed()->find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            if (!$category->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category is not deleted',
                    'data' => [
                        'category_id' => $category->id,
                        'category_name' => $category->name
                    ]
                ], 422);
            }

            $category->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'Category restored successfully',
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activate(string $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            if ($category->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category is already active',
                    'data' => [
                        'current_status' => 'active',
                        'category_id' => $category->id,
                        'category_name' => $category->name
                    ]
                ], 422);
            }

            $category->activate();

            return response()->json([
                'status' => 'success',
                'message' => 'Category activated successfully',
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            if (!$category->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category is already inactive',
                    'data' => [
                        'current_status' => 'inactive',
                        'category_id' => $category->id,
                        'category_name' => $category->name
                    ]
                ], 422);
            }

            $category->deactivate();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deactivated successfully',
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate category',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleFeatured(string $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found',
                    'data' => []
                ], 404);
            }

            $category->update([
                'is_featured' => !$category->is_featured
            ]);

            $status = $category->is_featured ? 'featured' : 'unfeatured';

            return response()->json([
                'status' => 'success',
                'message' => "Category {$status} successfully",
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle featured status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activeList(Request $request)
    {
        try {
            $categories = Category::active()->ordered()->get(['id', 'name', 'slug']);

            if ($categories->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No active categories found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Active categories retrieved successfully',
                'data' => $categories
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active categories',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
