<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\ProductReviewImage;
use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductReviewController extends Controller
{
    use FileUploadTrait;

    /**
     * Store a new product review
     */
    public function store(StoreProductReviewRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = auth('api')->user();
            $data = $request->validated();

            $orderItem = OrderItem::with('order.latestPayment')->findOrFail($data['order_item_id']);

            // 1. Verify ownership
            if ($orderItem->order->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to order item'
                ], 403);
            }

            // 2. Verify order is delivered
            if ($orderItem->order->order_status !== 'delivered') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You can only review items from delivered orders'
                ], 422);
            }

            // 3. Verify item hasn't been reviewed yet
            if ($orderItem->review()->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already reviewed this item'
                ], 422);
            }

            // 4. Create Review
            $review = ProductReview::create([
                'user_id' => $user->id,
                'product_id' => $orderItem->product_id,
                'variant_id' => $orderItem->variant_id,
                'order_item_id' => $orderItem->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'is_approved' => true, // Auto-approve for now
            ]);

            // 5. Handle Image Uploads
            if ($request->hasFile('images')) {
                $uploadedPaths = $this->handleMultipleFileUpload(
                    $request,
                    'images',
                    [],
                    'product/reviews',
                    'review_' . $review->id
                );

                foreach ($uploadedPaths as $path) {
                    ProductReviewImage::create([
                        'product_review_id' => $review->id,
                        'image_path' => $path
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Review submitted successfully',
                'data' => $review->load('images')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for a specific product
     */
    public function getProductReviews($productId): JsonResponse
    {
        try {
            $reviews = ProductReview::with(['user','product', 'variant', 'images'])
                ->where('product_id', $productId)
                ->where('is_approved', true)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'message' => 'Reviews retrieved successfully',
                'data' => $reviews
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
