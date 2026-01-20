<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CeateProductrequest;
use App\Http\Requests\UpdateProductrequest;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use FileUploadTrait;

    public function index(Request $request)
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
                'creator:id,name,email'
            ]);

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

    public function create() {}

    public function store(CeateProductrequest $request)
    {
        DB::beginTransaction();
        try {
            $currentUser = auth('api')->user();
            $data = $request->validated();

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            // Set default status if not provided
            if (!isset($data['status'])) {
                $data['status'] = 'draft';
            }

            $data['created_by'] = $currentUser->id;

            // Handle primary image upload
            $primaryImagePath = $this->handleFileUpload(
                $request,
                'primary_image_path',
                null,
                'products/primary_images/',
                $data['slug']
            );

            if ($primaryImagePath) {
                $data['primary_image_path'] = $primaryImagePath;
            }

            // Create product
            $product = Product::create($data);

            // handle gallery images
            $galleryImagePaths = $this->handleMultipleFileUpload(
                $request,
                'images',
                [],
                'products/' . $product->id . '/gallery',
                $data['slug'] . '_gallery'
            );

            // Save gallery images to database
            foreach ($galleryImagePaths as $imagePath) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                ]);
            }

            // Handle features
            if (!empty($data['feature_name'])) {
                if (is_string($data['feature_name'])) {
                    $featureNames = explode(',', $data['feature_name']);
                    $featureIds = [];

                    foreach ($featureNames as $featureName) {
                        $featureName = trim($featureName);

                        if ($featureName === '') {
                            continue;
                        }

                        $feature = Feature::firstOrCreate(
                            ['name' => $featureName],
                            ['slug' => Str::slug($featureName)]
                        );

                        $featureIds[] = $feature->id;
                    }

                    $product->features()->sync($featureIds);
                }
            }

            // Handle specifications
            if (!empty($data['specifications'])) {
                foreach ($data['specifications'] as $specification) {
                    ProductSpecification::create([
                        'product_id' => $product->id,
                        'specification_name' => $specification['specification_name'],
                        'specification_value' => $specification['specification_value']
                    ]);
                }
            }

            // Handle tags
            if (!empty($data['tags'])) {
                $tags = explode(',', $data['tags']);
                $tagIds = [];

                foreach ($tags as $tag) {
                    $tag = trim($tag);

                    if ($tag === '') {
                        continue;
                    }

                    $tagModel = Tag::firstOrCreate(
                        ['name' => $tag],
                        ['slug' => Str::slug($tag)]
                    );

                    $tagIds[] = $tagModel->id;
                }

                $product->tags()->sync($tagIds);
            }

            // Handle variants
            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $variantData) {
                    $variantData['product_id'] = $product->id;
                    $variantData['created_by'] = $currentUser->id;
                    ProductVariant::create($variantData);
                }
            }

            // Handle compatible products
            if (!empty($data['compatible_product_ids'])) {
                $compatibleIds = explode(',', $data['compatible_product_ids']);

                $compatibleIds = array_filter(array_map('trim', $compatibleIds), function ($id) {
                    return is_numeric($id) && $id > 0;
                });

                $product->compatibleProducts()->sync($compatibleIds);
            }

            // Handle bundled products
            if (!empty($data['bundled_product_ids'])) {
                $bundledIds = explode(',', $data['bundled_product_ids']);

                $bundledIds = array_filter(array_map('trim', $bundledIds), function ($id) {
                    return is_numeric($id) && $id > 0;
                });

                $product->bundledProducts()->sync($bundledIds);
            }

            DB::commit();

            // Load relationships
            $product->load(['category', 'brand', 'images', 'variants', 'features', 'specifications', 'tags', 'compatibleProducts', 'bundledProducts']);

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            // Cleanup uploaded files if error occurs
            if (isset($primaryImagePath)) {
                $this->deleteFile($primaryImagePath);
            }

            if (isset($galleryImagePaths) && !empty($galleryImagePaths)) {
                $this->deleteMultipleFiles($galleryImagePaths);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
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
                'creator:id,name,email'
            ])->find($id);

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

    public function edit(string $id) {}

    public function update(UpdateProductrequest $request, string $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            $data = $request->validated();

            // Update slug if name changed
            if (isset($data['name']) && $data['name'] !== $product->name) {
                $data['slug'] = Str::slug($data['name']);
            }

            // Handle primary image update
            $oldPrimaryImage = $product->primary_image_path;
            if ($request->hasFile('primary_image_path')) {
                $primaryImagePath = $this->handleFileUpload(
                    $request,
                    'primary_image_path',
                    $oldPrimaryImage,
                    'products/primary_images/',
                    $data['slug'] ?? $product->slug
                );

                if ($primaryImagePath) {
                    $data['primary_image_path'] = $primaryImagePath;
                }
            }

            // Update product
            $product->update($data);

            // Handle gallery images - add new ones
            $galleryImagePaths = [];
            if ($request->hasFile('images')) {

                $oldImagesGallery = $product->images()->pluck('image_path')->toArray();
                $this->deleteMultipleFiles($oldImagesGallery);
                $product->images()->delete();

                $galleryImagePaths = $this->handleMultipleFileUpload(
                    $request,
                    'images',
                    [],
                    'products/' . $product->id . '/gallery',
                    $product->slug . '_gallery'
                );

                foreach ($galleryImagePaths as $imagePath) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            // Handle features (replace all)
            if (isset($data['feature_name'])) {
                if (!empty($data['feature_name'])) {
                    $featureNames = explode(',', $data['feature_name']);
                    $featureIds = [];

                    foreach ($featureNames as $featureName) {
                        $featureName = trim($featureName);

                        if ($featureName === '') {
                            continue;
                        }

                        $feature = Feature::firstOrCreate(
                            ['name' => $featureName],
                            ['slug' => Str::slug($featureName)]
                        );

                        $featureIds[] = $feature->id;
                    }

                    $product->features()->sync($featureIds);
                } else {
                    $product->features()->detach();
                }
            }

            // Handle specifications (replace all)
            if (isset($data['specifications'])) {
                // Delete existing specifications
                $product->specifications()->delete();

                if (!empty($data['specifications'])) {
                    foreach ($data['specifications'] as $specification) {
                        ProductSpecification::create([
                            'product_id' => $product->id,
                            'specification_name' => $specification['specification_name'],
                            'specification_value' => $specification['specification_value']
                        ]);
                    }
                }
            }

            // Handle tags (replace all)
            if (isset($data['tags'])) {
                if (!empty($data['tags'])) {
                    $tags = explode(',', $data['tags']);
                    $tagIds = [];

                    foreach ($tags as $tag) {
                        $tag = trim($tag);

                        if ($tag === '') {
                            continue;
                        }

                        $tagModel = Tag::firstOrCreate(
                            ['name' => $tag],
                            ['slug' => Str::slug($tag)]
                        );

                        $tagIds[] = $tagModel->id;
                    }

                    $product->tags()->sync($tagIds);
                } else {
                    $product->tags()->detach();
                }
            }

            // Handle variants (update/create/delete)
            if (isset($data['variants'])) {
                $existingVariantIds = $product->variants()->pluck('id')->toArray();
                $updatedVariantIds = [];

                foreach ($data['variants'] as $variantData) {
                    if (isset($variantData['id'])) {
                        // Update existing variant
                        $variant = ProductVariant::find($variantData['id']);
                        if ($variant && $variant->product_id == $product->id) {
                            $variant->update($variantData);
                            $updatedVariantIds[] = $variantData['id'];
                        }
                    } else {
                        // Check if variant with same SKU already exists for this product
                        $existingVariant = ProductVariant::where('product_id', $product->id)
                            ->where('sku', $variantData['sku'])
                            ->first();

                        if ($existingVariant) {
                            // Update existing variant (found by SKU)
                            $existingVariant->update($variantData);
                            $updatedVariantIds[] = $existingVariant->id;
                        } else {
                            // Create new variant (only if SKU doesn't exist for this product)
                            $variantData['product_id'] = $product->id;
                            $variantData['created_by'] = auth('api')->user()->id;
                            $variant = ProductVariant::create($variantData);
                            $updatedVariantIds[] = $variant->id;
                        }
                    }
                }

                $variantsToDelete = array_diff($existingVariantIds, $updatedVariantIds);
                if (!empty($variantsToDelete)) {
                    ProductVariant::whereIn('id', $variantsToDelete)->delete();
                }
            }

            // Handle compatible products (replace all)
            if (isset($data['compatible_product_ids'])) {
                if (!empty($data['compatible_product_ids'])) {
                    $compatibleIds = explode(',', $data['compatible_product_ids']);

                    $compatibleIds = array_filter(array_map('trim', $compatibleIds), function ($id) {
                        return is_numeric($id) && $id > 0;
                    });

                    $product->compatibleProducts()->sync($compatibleIds);
                } else {
                    $product->compatibleProducts()->detach();
                }
            }

            // Handle bundled products (replace all)
            if (isset($data['bundled_product_ids'])) {
                if (!empty($data['bundled_product_ids'])) {
                    $bundledIds = explode(',', $data['bundled_product_ids']);

                    $bundledIds = array_filter(array_map('trim', $bundledIds), function ($id) {
                        return is_numeric($id) && $id > 0;
                    });

                    $product->bundledProducts()->sync($bundledIds);
                } else {
                    $product->bundledProducts()->detach();
                }
            }

            DB::commit();

            // Load updated relationships
            $product->refresh();
            $product->load(['category', 'brand', 'images', 'variants', 'features', 'specifications', 'tags', 'compatibleProducts', 'bundledProducts']);

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            if (isset($primaryImagePath)) {
                $this->deleteFile($primaryImagePath);
            }

            if (isset($galleryImagePaths) && !empty($galleryImagePaths)) {
                $this->deleteMultipleFiles($galleryImagePaths);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $product = Product::withTrashed()->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if (!$product->trashed()) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'Product is not deleted',
                    'data' => $product
                ], 200);
            }

            $product->restore();

            $product->load(['category', 'brand', 'images', 'variants', 'features', 'specifications', 'tags', 'compatibleProducts', 'bundledProducts']);

            return response()->json([
                'status' => 'success',
                'message' => 'Product restored successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function forceDestroy(string $id)
    {
        try {
            $product = Product::withTrashed()->with(['images'])->find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            $this->deleteFile($product->primary_image_path);

            // Delete associated images from storage
            $imagePaths = $product->images->pluck('image_path')->toArray();
            $this->deleteMultipleFiles($imagePaths);

            // Force delete the product
            $product->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product permanently deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to permanently delete product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activate(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if ($product->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already active',
                    'data' => [
                        'current_status' => 'active',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]
                ], 422);
            }

            $product->activate();

            return response()->json([
                'status' => 'success',
                'message' => 'Product activated successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function deactivate(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if (!$product->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already inactive',
                    'data' => [
                        'current_status' => 'inactive',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]
                ], 422);
            }

            $product->deactivate();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deactivated successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to deactivate product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function publish(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if ($product->status === 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already published',
                    'data' => [
                        'current_status' => 'published',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]
                ], 422);
            }

            $product->publish();

            return response()->json([
                'status' => 'success',
                'message' => 'Product published successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function archive(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if ($product->status === 'archived') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already archived',
                    'data' => [
                        'current_status' => 'archived',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]
                ], 422);
            }

            $product->archive();

            return response()->json([
                'status' => 'success',
                'message' => 'Product archived successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to archive product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function setAsDraft(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if ($product->status === 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product is already in draft',
                    'data' => [
                        'current_status' => 'draft',
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]
                ], 422);
            }

            $product->setAsDraft();

            return response()->json([
                'status' => 'success',
                'message' => 'Product set as draft successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set product as draft',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleTrending(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            $product->toggleTrending();

            $status = $product->is_trending ? 'trending' : 'not trending';

            return response()->json([
                'status' => 'success',
                'message' => "Product marked as {$status} successfully",
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle trending status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function toggleFeatured(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            $product->toggleFeatured();

            $status = $product->is_featured ? 'featured' : 'unfeatured';

            return response()->json([
                'status' => 'success',
                'message' => "Product {$status} successfully",
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle featured status',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function removePrimaryImage(string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            if (!$product->primary_image_path) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No primary image to remove',
                    'data' => [
                        'product_id' => $product->id,
                        'product_name' => $product->name
                    ]
                ], 422);
            }

            $this->deleteFile($product->primary_image_path);

            $product->update(['primary_image_path' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'Primary image removed successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove primary image',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function updatePrimaryImage(Request $request, string $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }

            $request->validate([
                'primary_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);

            $oldImagePath = $product->primary_image_path;
            $imagePath = $this->handleFileUpload(
                $request,
                'primary_image',
                $oldImagePath,
                'products',
                $product->slug
            );

            if ($imagePath) {
                $product->update(['primary_image_path' => $imagePath]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Primary image updated successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update primary image',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function activateVariant(string $id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found',
                    'data' => []
                ], 404);
            }

            if ($variant->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant is already active',
                    'data' => [
                        'current_status' => 'active',
                        'variant_id' => $variant->id,
                        'variant_name' => $variant->variant_name
                    ]
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

    public function deactivateVariant(string $id)
    {
        try {
            $variant = ProductVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant not found',
                    'data' => []
                ], 404);
            }

            if (!$variant->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product variant is already inactive',
                    'data' => [
                        'current_status' => 'inactive',
                        'variant_id' => $variant->id,
                        'variant_name' => $variant->variant_name
                    ]
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

    public function getTags()
    {
        try {
            $tags = Tag::select('id', 'name')->orderBy('name')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Tags retrieved successfully',
                'data' => $tags
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve tags',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function getFeatures()
    {
        try {
            $features = Feature::select('id', 'name')->orderBy('name')->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Features retrieved successfully',
                'data' => $features
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve features',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
