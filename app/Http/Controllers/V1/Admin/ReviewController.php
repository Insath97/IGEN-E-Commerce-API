<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\LogsActivity;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReviewController extends Controller implements HasMiddleware
{
    use LogsActivity;
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Review Index', only: ['index', 'show']),
            new Middleware('permission:Review Status Update', only: ['toggleStatus']),
            new Middleware('permission:Review Delete', only: ['destroy']),
        ];
    }
    /**
     * List all reviews with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProductReview::with(['user', 'product', 'variant', 'images', 'orderItem']);

            if ($request->has('status')) {
                $query->where('is_approved', $request->status === 'approved');
            }

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            $reviews = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'message' => 'Reviews retrieved successfully',
                'data' => $reviews
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve reviews',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get review details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $review = ProductReview::with(['user', 'product', 'variant', 'images', 'orderItem.order'])->find($id);

            if (!$review) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Review not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Review details retrieved successfully',
                'data' => $review
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve review details',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Toggle approval status of a review
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $review = ProductReview::find($id);

            if (!$review) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Review not found',
                ], 404);
            }

            $review->update([
                'is_approved' => !$review->is_approved
            ]);

            $statusMessage = $review->is_approved ? 'Review approved successfully' : 'Review unapproved successfully';

            $this->logActivity('Review', 'Toggle Approval', "Toggled approval for review by {$review->user->name} on {$review->product->name}", ['is_approved' => $review->is_approved]);

            return response()->json([
                'status' => 'success',
                'message' => $statusMessage,
                'data' => $review->fresh('images')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle review status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a review
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $review = ProductReview::find($id);

            if (!$review) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Review not found',
                ], 404);
            }

            // Optional: Delete images from storage (FileUploadTrait could be used here)
            $review->delete();

            $this->logActivity('Review', 'Delete', "Deleted review by {$review->user->name} on {$review->product->name}");

            return response()->json([
                'status' => 'success',
                'message' => 'Review deleted successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete review',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
