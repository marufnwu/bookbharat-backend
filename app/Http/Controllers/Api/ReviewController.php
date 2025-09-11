<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Display reviews for a product.
     */
    public function index(Request $request, $productId)
    {
        try {
            $product = Product::findOrFail($productId);

            $query = Review::with(['user:id,name', 'order:id,order_number'])
                ->where('product_id', $productId)
                ->approved();

            // Apply filters
            if ($request->rating) {
                $query->where('rating', $request->rating);
            }

            if ($request->verified_only) {
                $query->verifiedPurchase();
            }

            // Sorting
            switch ($request->sort) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'rating_high':
                    $query->orderBy('rating', 'desc');
                    break;
                case 'rating_low':
                    $query->orderBy('rating', 'asc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $perPage = min($request->get('per_page', 10), 50);
            $reviews = $query->paginate($perPage);

            // Calculate rating summary
            $ratingSummary = Review::where('product_id', $productId)
                ->approved()
                ->selectRaw('
                    AVG(rating) as average_rating,
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                    SUM(CASE WHEN is_verified_purchase = 1 THEN 1 ELSE 0 END) as verified_reviews
                ')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reviews' => $reviews,
                    'summary' => [
                        'average_rating' => round($ratingSummary->average_rating ?? 0, 2),
                        'total_reviews' => $ratingSummary->total_reviews ?? 0,
                        'verified_reviews' => $ratingSummary->verified_reviews ?? 0,
                        'rating_breakdown' => [
                            '5' => $ratingSummary->five_star ?? 0,
                            '4' => $ratingSummary->four_star ?? 0,
                            '3' => $ratingSummary->three_star ?? 0,
                            '2' => $ratingSummary->two_star ?? 0,
                            '1' => $ratingSummary->one_star ?? 0,
                        ]
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve reviews'
            ], 500);
        }
    }

    /**
     * Store a new review.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'order_id' => 'sometimes|nullable|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'comment' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::findOrFail($request->product_id);

            // Check if user already reviewed this product
            $existingReview = Review::where('user_id', auth()->id())
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already reviewed this product'
                ], 409);
            }

            $isVerifiedPurchase = false;
            $orderId = null;

            // If order_id is provided, verify it belongs to the user and contains the product
            if ($request->order_id) {
                $order = Order::where('id', $request->order_id)
                    ->where('user_id', auth()->id())
                    ->first();

                if ($order) {
                    $orderItem = OrderItem::where('order_id', $order->id)
                        ->where('product_id', $request->product_id)
                        ->first();

                    if ($orderItem) {
                        $isVerifiedPurchase = true;
                        $orderId = $order->id;
                    }
                }
            } else {
                // Check if user has purchased this product in any order
                $purchaseExists = OrderItem::whereHas('order', function ($query) {
                    $query->where('user_id', auth()->id())
                          ->where('status', 'delivered');
                })
                ->where('product_id', $request->product_id)
                ->first();

                if ($purchaseExists) {
                    $isVerifiedPurchase = true;
                    $orderId = $purchaseExists->order_id;
                }
            }

            $review = Review::create([
                'user_id' => auth()->id(),
                'product_id' => $request->product_id,
                'order_id' => $orderId,
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'is_verified_purchase' => $isVerifiedPurchase,
                'is_approved' => true // Auto-approve for now, can be changed to false for moderation
            ]);

            $review->load(['user:id,name', 'order:id,order_number']);

            return response()->json([
                'status' => 'success',
                'message' => 'Review submitted successfully',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit review'
            ], 500);
        }
    }

    /**
     * Display user's reviews.
     */
    public function userReviews(Request $request)
    {
        try {
            $query = Review::with(['product:id,name,slug,featured_image', 'order:id,order_number'])
                ->where('user_id', auth()->id());

            // Apply filters
            if ($request->rating) {
                $query->where('rating', $request->rating);
            }

            if ($request->status) {
                switch ($request->status) {
                    case 'approved':
                        $query->where('is_approved', true);
                        break;
                    case 'pending':
                        $query->where('is_approved', false);
                        break;
                }
            }

            $query->orderBy('created_at', 'desc');

            $perPage = min($request->get('per_page', 15), 50);
            $reviews = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $reviews
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user reviews'
            ], 500);
        }
    }

    /**
     * Update a review.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'title' => 'sometimes|string|max:255',
            'comment' => 'sometimes|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review = Review::where('user_id', auth()->id())
                ->findOrFail($id);

            $review->update($request->only(['rating', 'title', 'comment']));

            // If review was previously approved and now updated, might need re-approval
            // $review->update(['is_approved' => false]);

            $review->load(['user:id,name', 'product:id,name', 'order:id,order_number']);

            return response()->json([
                'status' => 'success',
                'message' => 'Review updated successfully',
                'data' => $review
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update review'
            ], 500);
        }
    }

    /**
     * Delete a review.
     */
    public function destroy($id)
    {
        try {
            $review = Review::where('user_id', auth()->id())
                ->findOrFail($id);

            $review->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Review deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete review'
            ], 500);
        }
    }

    /**
     * Get products eligible for review by the user.
     */
    public function eligibleProducts(Request $request)
    {
        try {
            // Get products from delivered orders that haven't been reviewed yet
            $query = OrderItem::with(['product:id,name,slug,featured_image', 'order:id,order_number,created_at'])
                ->whereHas('order', function ($q) {
                    $q->where('user_id', auth()->id())
                      ->where('status', 'delivered');
                })
                ->whereDoesntHave('product.reviews', function ($q) {
                    $q->where('user_id', auth()->id());
                })
                ->select(['id', 'order_id', 'product_id', 'created_at']);

            $orderItems = $query->get()->unique('product_id');

            $eligibleProducts = $orderItems->map(function ($item) {
                return [
                    'order_item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_number' => $item->order->order_number,
                    'order_date' => $item->order->created_at,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'slug' => $item->product->slug,
                        'image' => $item->product->featured_image
                    ]
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $eligibleProducts->values()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve eligible products'
            ], 500);
        }
    }

    /**
     * Get review statistics for the authenticated user.
     */
    public function userStats()
    {
        try {
            $stats = Review::where('user_id', auth()->id())
                ->selectRaw('
                    COUNT(*) as total_reviews,
                    AVG(rating) as avg_rating_given,
                    COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_reviews,
                    COUNT(CASE WHEN is_verified_purchase = 1 THEN 1 END) as verified_reviews
                ')
                ->first();

            $ratingBreakdown = Review::where('user_id', auth()->id())
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->pluck('count', 'rating');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_reviews' => $stats->total_reviews ?? 0,
                    'average_rating_given' => round($stats->avg_rating_given ?? 0, 2),
                    'approved_reviews' => $stats->approved_reviews ?? 0,
                    'verified_reviews' => $stats->verified_reviews ?? 0,
                    'rating_breakdown' => $ratingBreakdown
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve review statistics'
            ], 500);
        }
    }

    /**
     * Report a review as inappropriate.
     */
    public function report(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
            'description' => 'sometimes|nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review = Review::findOrFail($id);

            // Prevent users from reporting their own reviews
            if ($review->user_id === auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot report your own review'
                ], 400);
            }

            // For now, we'll just log the report
            // In a full implementation, you'd store reports in a separate table
            \Log::info('Review reported', [
                'review_id' => $id,
                'reported_by' => auth()->id(),
                'reason' => $request->reason,
                'description' => $request->description
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Review has been reported and will be reviewed by our team'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to report review'
            ], 500);
        }
    }
}