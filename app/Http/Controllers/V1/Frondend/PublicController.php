<?php

namespace App\Http\Controllers\V1\Frondend;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMail;
use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

class PublicController extends Controller
{
    /**
     * Send contact mail and store in DB.
     */
    public function sendContactMail(ContactRequest $request)
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            
            // Store in database
            Contact::create($data);

            $shopEmail = Setting::getValue('shop_email', config('mail.from.address'));

            // Specialized try-catch for mailing
            try {
                Mail::to($shopEmail)->send(new ContactMail($data));
            } catch (\Exception $mailException) {
                // Log the mail error but proceed since DB storage was successful
                \Log::error('Contact Mail Error: ' . $mailException->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Your message has been received and stored. We will get back to you soon.'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process your request. Please try again later.',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

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
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
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
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function productsGetAll(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $query = Product::select([
                'id',
                'name',
                'slug',
                'code',
                'category_id',
                'brand_id',
                'type',
                'short_description',
                'full_description',
                'primary_image_path',
                'type',
                'created_by',
                'status',
                'is_trending',
                'is_active',
                'is_featured',
                'condition'
            ])->with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants:id,product_id,variant_name,sku,barcode,warranty_period,storage_size,ram_size,color,price,sales_price,stock_quantity,low_stock_threshold,is_offer,offer_price,is_trending,is_active,is_featured',
                'features:id,name',
                'specifications:id,product_id,specification_name,specification_value',
                'tags:id,name,slug',
                'compatibleProducts:id,name,primary_image_path',
                'bundledProducts:id,name,primary_image_path',
                'reviews' => fn($q) => $q->where('is_approved', true)->with('user:id,name,profile_image')->latest(),
                'reviews.images',
                'variants.reviews' => fn($q) => $q->where('is_approved', true)->latest(),
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
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function ProductById(string $id)
    {
        try {
            $product = Product::select([
                'id',
                'name',
                'slug',
                'code',
                'category_id',
                'brand_id',
                'type',
                'short_description',
                'full_description',
                'primary_image_path',
                'type',
                'created_by',
                'status',
                'is_trending',
                'is_active',
                'is_featured',
                'condition'
            ])->with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants:id,product_id,variant_name,sku,barcode,warranty_period,storage_size,ram_size,color,price,sales_price,stock_quantity,low_stock_threshold,is_offer,offer_price,is_trending,is_active,is_featured',
                'features:id,name',
                'specifications:id,product_id,specification_name,specification_value',
                'tags:id,name,slug',
                'compatibleProducts:id,name,primary_image_path',
                'bundledProducts:id,name,primary_image_path',
                'reviews' => fn($q) => $q->where('is_approved', true)->with('user:id,name,profile_image')->latest(),
                'reviews.images',
                'variants.reviews' => fn($q) => $q->where('is_approved', true)->latest(),
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
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* trending product list */
    public function trendingProducts(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $products = Product::select([
                'id',
                'name',
                'slug',
                'code',
                'category_id',
                'brand_id',
                'type',
                'short_description',
                'primary_image_path',
                'is_trending',
                'is_active',
                'condition'
            ])->with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants:id,product_id,variant_name,sku,price,sales_price,stock_quantity,is_offer,offer_price',
            ])
                ->active()
                ->published()
                ->trending()
                ->ordered()
                ->limit($limit)
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No trending products found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Trending products retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve trending products',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* featured product list */
    public function featuredProducts(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $products = Product::select([
                'id',
                'name',
                'slug',
                'code',
                'category_id',
                'brand_id',
                'type',
                'short_description',
                'primary_image_path',
                'is_featured',
                'is_active',
                'condition'
            ])->with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants:id,product_id,variant_name,sku,price,sales_price,stock_quantity,is_offer,offer_price',
            ])
                ->active()
                ->published()
                ->featured()
                ->ordered()
                ->limit($limit)
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No featured products found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Featured products retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve featured products',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* new arrival product list */
    public function newArrivalProducts(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $products = Product::select([
                'id',
                'name',
                'slug',
                'code',
                'category_id',
                'brand_id',
                'type',
                'short_description',
                'primary_image_path',
                'is_new_arrival',
                'is_active',
                'condition'
            ])->with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants:id,product_id,variant_name,sku,price,sales_price,stock_quantity,is_offer,offer_price',
            ])
                ->active()
                ->published()
                ->newArrival()
                ->ordered()
                ->limit($limit)
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No new arrival products found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'New arrival products retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve new arrival products',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* offer product list */
    public function offerProducts(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $products = Product::select([
                'id',
                'name',
                'slug',
                'code',
                'category_id',
                'brand_id',
                'type',
                'short_description',
                'primary_image_path',
                'is_active',
                'condition'
            ])->with([
                'category:id,name,slug',
                'brand:id,name,slug,logo,website',
                'images:id,product_id,image_path',
                'variants' => function ($query) {
                    $query->select('id', 'product_id', 'variant_name', 'sku', 'price', 'sales_price', 'stock_quantity', 'is_offer', 'offer_price')
                          ->where('is_offer', true)
                          ->whereNotNull('offer_price')
                          ->where('offer_price', '>', 0);
                },
            ])
                ->whereHas('variants', function ($query) {
                    $query->where('is_offer', true)
                          ->whereNotNull('offer_price')
                          ->where('offer_price', '>', 0);
                })
                ->active()
                ->published()
                ->ordered()
                ->limit($limit)
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No offer products found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Offer products retrieved successfully',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve offer products',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* trending variant list */
    public function trendingVariants(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $variants = \App\Models\ProductVariant::with(['product:id,name,slug,primary_image_path'])
                ->active()
                ->trending()
                ->limit($limit)
                ->get();

            if ($variants->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No trending variants found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Trending variants retrieved successfully',
                'data' => $variants
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve trending variants',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* featured variant list */
    public function featuredVariants(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $variants = \App\Models\ProductVariant::with(['product:id,name,slug,primary_image_path'])
                ->active()
                ->featured()
                ->limit($limit)
                ->get();

            if ($variants->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No featured variants found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Featured variants retrieved successfully',
                'data' => $variants
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve featured variants',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* new arrival variant list */
    public function newArrivalVariants(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $variants = \App\Models\ProductVariant::with(['product:id,name,slug,primary_image_path'])
                ->active()
                ->newArrival()
                ->limit($limit)
                ->get();

            if ($variants->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No new arrival variants found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'New arrival variants retrieved successfully',
                'data' => $variants
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve new arrival variants',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /* offer variant list */
    public function offerVariants(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $variants = \App\Models\ProductVariant::with(['product:id,name,slug,primary_image_path'])
                ->active()
                ->onOffer()
                ->limit($limit)
                ->get();

            if ($variants->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No offer variants found',
                    'data' => []
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Offer variants retrieved successfully',
                'data' => $variants
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve offer variants',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
