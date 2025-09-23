<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Display a listing of all reviews
     */
    public function index(Request $request)
    {
        $query = Review::with(['product:id,name,slug', 'user:id,name,email']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $reviews = $query->paginate($request->get('per_page', 20));

        // Get statistics
        $stats = [
            'total' => Review::count(),
            'pending' => Review::where('status', 'pending')->count(),
            'approved' => Review::where('status', 'approved')->count(),
            'rejected' => Review::where('status', 'rejected')->count(),
            'reported' => Review::where('is_reported', true)->count(),
            'average_rating' => Review::where('status', 'approved')->avg('rating') ?? 0,
            'rating_distribution' => Review::where('status', 'approved')
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->pluck('count', 'rating')
        ];

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'stats' => $stats
        ]);
    }

    /**
     * Display the specified review
     */
    public function show(Review $review)
    {
        $review->load(['product', 'user', 'images']);

        // Get previous reviews by the same user
        $userReviews = Review::where('user_id', $review->user_id)
            ->where('id', '!=', $review->id)
            ->select('id', 'product_id', 'rating', 'created_at')
            ->with('product:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'review' => $review,
            'user_review_history' => $userReviews
        ]);
    }

    /**
     * Approve a review
     */
    public function approve(Review $review)
    {
        if ($review->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Review is already approved'
            ], 422);
        }

        $review->update([
            'status' => 'approved',
            'moderated_by' => auth()->id(),
            'moderated_at' => now()
        ]);

        // Update product average rating
        $this->updateProductRating($review->product_id);

        return response()->json([
            'success' => true,
            'message' => 'Review approved successfully',
            'review' => $review
        ]);
    }

    /**
     * Reject a review
     */
    public function reject(Request $request, Review $review)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        if ($review->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Review is already rejected'
            ], 422);
        }

        $review->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
            'moderated_by' => auth()->id(),
            'moderated_at' => now()
        ]);

        // Update product average rating
        $this->updateProductRating($review->product_id);

        return response()->json([
            'success' => true,
            'message' => 'Review rejected successfully',
            'review' => $review
        ]);
    }

    /**
     * Remove the specified review
     */
    public function destroy(Review $review)
    {
        $productId = $review->product_id;

        // Delete associated images if any
        if ($review->images) {
            foreach ($review->images as $image) {
                \Storage::disk('public')->delete($image);
            }
        }

        $review->delete();

        // Update product average rating
        $this->updateProductRating($productId);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * Bulk action on reviews
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,reject,delete',
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:reviews,id',
            'reason' => 'required_if:action,reject|string|max:500'
        ]);

        $reviews = Review::whereIn('id', $request->review_ids)->get();
        $affectedProducts = $reviews->pluck('product_id')->unique();

        DB::beginTransaction();
        try {
            foreach ($reviews as $review) {
                switch ($request->action) {
                    case 'approve':
                        if ($review->status !== 'approved') {
                            $review->update([
                                'status' => 'approved',
                                'moderated_by' => auth()->id(),
                                'moderated_at' => now()
                            ]);
                        }
                        break;

                    case 'reject':
                        if ($review->status !== 'rejected') {
                            $review->update([
                                'status' => 'rejected',
                                'rejection_reason' => $request->reason,
                                'moderated_by' => auth()->id(),
                                'moderated_at' => now()
                            ]);
                        }
                        break;

                    case 'delete':
                        // Delete associated images
                        if ($review->images) {
                            foreach ($review->images as $image) {
                                \Storage::disk('public')->delete($image);
                            }
                        }
                        $review->delete();
                        break;
                }
            }

            // Update product ratings for affected products
            foreach ($affectedProducts as $productId) {
                $this->updateProductRating($productId);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk action completed successfully',
                'affected_count' => count($request->review_ids)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete bulk action: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending reviews
     */
    public function getPending(Request $request)
    {
        $query = Review::where('status', 'pending')
            ->with(['product:id,name,slug', 'user:id,name,email'])
            ->orderBy('created_at', 'asc');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'total_pending' => Review::where('status', 'pending')->count()
        ]);
    }

    /**
     * Get reported reviews
     */
    public function getReported(Request $request)
    {
        $query = Review::where('is_reported', true)
            ->with(['product:id,name,slug', 'user:id,name,email'])
            ->orderBy('report_count', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reviews = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'reviews' => $reviews,
            'total_reported' => Review::where('is_reported', true)->count()
        ]);
    }

    /**
     * Update product average rating
     */
    private function updateProductRating($productId)
    {
        $product = Product::find($productId);
        if (!$product) return;

        $avgRating = Review::where('product_id', $productId)
            ->where('status', 'approved')
            ->avg('rating');

        $reviewCount = Review::where('product_id', $productId)
            ->where('status', 'approved')
            ->count();

        $product->update([
            'rating' => $avgRating ?? 0,
            'review_count' => $reviewCount
        ]);
    }
}