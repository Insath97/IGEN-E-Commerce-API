<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBrandrequest;
use App\Http\Requests\UpdateBrandrequest;
use App\Models\Brand;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    use FileUploadTrait;

    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Brand::query();

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

            $brands = $query->paginate($perPage);

            if ($brands->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No brands found',
                    'data' => []
                ], 200);
            }

            // Transform to include logo URL
            $brands->getCollection()->transform(function ($brand) {
                $brand->logo_url = $brand->logo_url ?? null;
                return $brand;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Brands retrieved successfully',
                'data' => $brands
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve brands',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function create() {}

    public function store(CreateBrandrequest $request)
    {
        try {
            $currentUser = auth('api')->user();
            $data = $request->validated();

            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $imagePath = $this->handleFileUpload($request, 'logo', null, 'brands', $data['slug'] ?? '');
            if ($imagePath) {
                $data['logo'] = $imagePath ?? null;
            }

            $data['created_by'] = $currentUser->id;

            $brand = Brand::create($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Brand created successfully',
                'data' => $brand
            ], 201);
        } catch (\Throwable $th) {
            if (isset($imagePath)) {
                $this->deleteFile($imagePath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Brand retrieved successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function edit(string $id) {}

    public function update(UpdateBrandrequest $request, string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            $data = $request->validated();

            if (isset($data['name']) && $data['name'] !== $brand->name) {
                $data['slug'] = Str::slug($data['name']);
            }

            $oldLogoPath = $brand->logo;
            $imagePath = $this->handleFileUpload($request, 'logo', $oldLogoPath, 'brands', $data['slug'] ?? $brand->slug ?? $brand->name);

            if ($imagePath) {
                $data['logo'] = $imagePath;
            }

            $brand->update($data);
            $brand->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand updated successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            $brand->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function  forceDestroy(string $id)
    {
        try {
            $brand = Brand::withTrashed()->find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            if ($brand->logo) {
                $this->deleteFile($brand->logo);
            }

            $brand->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand permanently deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to permanently delete brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $brand = Brand::withTrashed()->find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            if (!$brand->trashed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand is not deleted',
                    'data' => [
                        'brand_id' => $brand->id,
                        'brand_name' => $brand->name
                    ]
                ], 422);
            }

            $brand->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand restored successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activateBrand(string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            if ($brand->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand is already active',
                    'data' => [
                        'current_status' => 'active',
                        'brand_id' => $brand->id,
                        'brand_name' => $brand->name
                    ]
                ], 422);
            }

            $brand->activate();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand activated successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function deactivateBrand(string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            if (!$brand->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand is already inactive',
                    'data' => [
                        'current_status' => 'inactive',
                        'brand_id' => $brand->id,
                        'brand_name' => $brand->name
                    ]
                ], 422);
            }

            $brand->deactivate();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand deactivated successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate brand',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function removeLogo(string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            if (!$brand->logo) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No logo to remove',
                    'data' => [
                        'brand_id' => $brand->id,
                        'brand_name' => $brand->name
                    ]
                ], 422);
            }

            $this->deleteFile($brand->logo);

            $brand->update(['logo' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'Logo removed successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove logo',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updateLogo(Request $request, string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            if (!$request->hasFile('logo')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No logo file provided',
                    'data' => [
                        'brand_id' => $brand->id,
                        'brand_name' => $brand->name
                    ]
                ], 422);
            }

            $imagePath = $this->handleFileUpload($request, 'logo', $brand->logo, 'brands', $brand->slug ?? $brand->name);

            if ($imagePath) {
                $brand->update(['logo' => $imagePath]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logo updated successfully',
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update logo',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleFeatured(string $id)
    {
        try {
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Brand not found',
                    'data' => []
                ], 404);
            }

            $brand->update([
                'is_featured' => !$brand->is_featured
            ]);

            $status = $brand->is_featured ? 'featured' : 'unfeatured';

            return response()->json([
                'status' => 'success',
                'message' => "Brand {$status} successfully",
                'data' => $brand
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle featured status',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
