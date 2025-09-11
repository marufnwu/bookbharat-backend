<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MobileOptimizedController extends Controller
{
    public function getQuickActions()
    {
        $user = Auth::user();
        
        $actions = [
            [
                'id' => 'search',
                'title' => 'Search Books',
                'icon' => 'search',
                'route' => '/search',
                'color' => '#3b82f6'
            ],
            [
                'id' => 'categories',
                'title' => 'Browse Categories',
                'icon' => 'grid',
                'route' => '/categories',
                'color' => '#10b981'
            ],
            [
                'id' => 'wishlist',
                'title' => 'My Wishlist',
                'icon' => 'heart',
                'route' => '/wishlist',
                'color' => '#f59e0b',
                'badge' => $user ? $user->wishlists()->count() : 0
            ],
            [
                'id' => 'orders',
                'title' => 'My Orders',
                'icon' => 'package',
                'route' => '/orders',
                'color' => '#8b5cf6',
                'badge' => $user ? $user->orders()->whereIn('status', ['pending', 'processing'])->count() : 0
            ]
        ];

        if ($user) {
            $actions[] = [
                'id' => 'loyalty',
                'title' => 'Loyalty Points',
                'icon' => 'star',
                'route' => '/loyalty',
                'color' => '#f97316',
                'badge' => $user->loyaltyAccount ? $user->loyaltyAccount->points_balance : 0
            ];
        }

        return response()->json([
            'success' => true,
            'actions' => $actions
        ]);
    }

    public function getMobileSearchSuggestions(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return $this->getPopularSearches();
        }

        $cacheKey = 'mobile_search_suggestions_' . md5($query);
        
        $suggestions = Cache::remember($cacheKey, 300, function () use ($query) {
            $products = Product::active()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', '%' . $query . '%')
                      ->orWhere('description', 'like', '%' . $query . '%')
                      ->orWhere('author', 'like', '%' . $query . '%');
                })
                ->select(['id', 'name', 'author', 'primary_image', 'price'])
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->name,
                        'subtitle' => $product->author,
                        'image' => $product->primary_image_url,
                        'price' => $product->formatted_price,
                        'type' => 'product'
                    ];
                });

            $categories = Category::active()
                ->where('name', 'like', '%' . $query . '%')
                ->select(['id', 'name', 'slug'])
                ->limit(3)
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->name,
                        'subtitle' => 'Category',
                        'type' => 'category'
                    ];
                });

            return [
                'products' => $products,
                'categories' => $categories
            ];
        });

        return response()->json([
            'success' => true,
            'query' => $query,
            'suggestions' => $suggestions
        ]);
    }

    public function getPopularSearches()
    {
        $popularSearches = Cache::remember('mobile_popular_searches', 3600, function () {
            return [
                ['term' => 'Fiction Books', 'count' => 1250],
                ['term' => 'Mystery & Thriller', 'count' => 890],
                ['term' => 'Self Help', 'count' => 756],
                ['term' => 'Biography', 'count' => 634],
                ['term' => 'Science Fiction', 'count' => 567],
                ['term' => 'Romance', 'count' => 432],
                ['term' => 'Philosophy', 'count' => 345],
                ['term' => 'History', 'count' => 289]
            ];
        });

        return response()->json([
            'success' => true,
            'popular_searches' => $popularSearches
        ]);
    }

    public function getMobileProductCard(Product $product)
    {
        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'author' => $product->author,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'formatted_price' => $product->formatted_price,
            'formatted_compare_price' => $product->formatted_compare_price,
            'discount_percentage' => $product->discount_percentage,
            'primary_image' => $product->primary_image_url,
            'average_rating' => $product->average_rating,
            'total_reviews' => $product->total_reviews,
            'in_stock' => $product->in_stock,
            'stock_quantity' => $product->stock_quantity,
            'is_featured' => $product->is_featured,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name
            ] : null,
            'badges' => $this->getProductBadges($product),
            'quick_actions' => $this->getProductQuickActions($product),
        ];

        if (Auth::check()) {
            $user = Auth::user();
            $productData['is_wishlisted'] = $user->wishlists()
                ->where('product_id', $product->id)
                ->exists();
            
            $productData['in_cart'] = $user->cart()
                ->where('product_id', $product->id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'product' => $productData
        ]);
    }

    public function getMobileOrderSummary(Order $order)
    {
        if (!Auth::check() || $order->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => ucfirst($order->status),
            'total_amount' => $order->total_amount,
            'formatted_total_amount' => $order->formatted_total_amount,
            'created_at' => $order->created_at->format('M d, Y'),
            'estimated_delivery' => $this->getEstimatedDelivery($order),
            'tracking_info' => $this->getTrackingInfo($order),
            'items_count' => $order->orderItems()->count(),
            'items_preview' => $order->orderItems()
                ->with('product:id,name,primary_image')
                ->limit(3)
                ->get()
                ->map(function ($item) {
                    return [
                        'product_name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'image' => $item->product->primary_image_url
                    ];
                }),
            'can_cancel' => $order->can_be_cancelled,
            'can_return' => in_array($order->status, ['delivered']),
            'actions' => $this->getOrderActions($order)
        ];

        return response()->json([
            'success' => true,
            'order' => $orderData
        ]);
    }

    public function getMobileUserStats()
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        
        $stats = [
            'orders' => [
                'total' => $user->orders()->count(),
                'pending' => $user->orders()->where('status', 'pending')->count(),
                'delivered' => $user->orders()->where('status', 'delivered')->count(),
                'total_spent' => $user->orders()->where('status', 'delivered')->sum('total_amount')
            ],
            'wishlist' => [
                'total_items' => $user->wishlists()->count(),
                'categories_count' => $user->wishlists()
                    ->join('products', 'wishlists.product_id', '=', 'products.id')
                    ->distinct('products.category_id')
                    ->count()
            ],
            'loyalty' => [
                'points_balance' => $user->loyaltyAccount ? $user->loyaltyAccount->points_balance : 0,
                'tier' => $user->loyaltyTier ? $user->loyaltyTier->name : 'Bronze',
                'points_earned_this_month' => $user->points()
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('points')
            ],
            'profile_completion' => $this->calculateProfileCompletion($user),
            'recommendations_count' => $user->recommendations()->count(),
            'reviews_count' => $user->reviews()->count()
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    public function getMobileDashboard()
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        $dashboard = [
            'user' => [
                'name' => $user->name,
                'avatar' => $user->avatar_url,
                'tier' => $user->loyaltyTier ? $user->loyaltyTier->name : 'Bronze'
            ],
            'quick_stats' => [
                'cart_items' => $user->cart()->sum('quantity'),
                'wishlist_items' => $user->wishlists()->count(),
                'loyalty_points' => $user->loyaltyAccount ? $user->loyaltyAccount->points_balance : 0,
                'pending_orders' => $user->orders()->whereIn('status', ['pending', 'processing'])->count()
            ],
            'recent_orders' => $user->orders()
                ->latest()
                ->limit(3)
                ->with('orderItems.product:id,name,primary_image')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total_amount' => $order->formatted_total_amount,
                        'items_count' => $order->orderItems->count(),
                        'created_at' => $order->created_at->format('M d, Y')
                    ];
                }),
            'personalized_recommendations' => $user->recommendations()
                ->with('product:id,name,author,price,primary_image,average_rating')
                ->limit(6)
                ->get()
                ->map(function ($rec) {
                    return [
                        'product' => $rec->product,
                        'reason' => $rec->reason
                    ];
                }),
            'notifications' => $user->unreadNotifications()
                ->limit(5)
                ->get(),
            'achievements' => $this->getUserAchievements($user),
        ];

        return response()->json([
            'success' => true,
            'dashboard' => $dashboard
        ]);
    }

    protected function getProductBadges(Product $product): array
    {
        $badges = [];

        if ($product->is_featured) {
            $badges[] = ['text' => 'Featured', 'color' => '#f59e0b'];
        }

        if ($product->discount_percentage > 0) {
            $badges[] = ['text' => $product->discount_percentage . '% Off', 'color' => '#ef4444'];
        }

        if ($product->average_rating >= 4.5) {
            $badges[] = ['text' => 'Bestseller', 'color' => '#10b981'];
        }

        if ($product->created_at->diffInDays(now()) <= 30) {
            $badges[] = ['text' => 'New', 'color' => '#8b5cf6'];
        }

        return $badges;
    }

    protected function getProductQuickActions(Product $product): array
    {
        $actions = [];

        if (Auth::check()) {
            $user = Auth::user();
            
            $isWishlisted = $user->wishlists()->where('product_id', $product->id)->exists();
            $actions[] = [
                'id' => 'wishlist',
                'icon' => $isWishlisted ? 'heart-filled' : 'heart',
                'label' => $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist',
                'active' => $isWishlisted
            ];

            $actions[] = [
                'id' => 'cart',
                'icon' => 'shopping-cart',
                'label' => 'Add to Cart',
                'primary' => true
            ];
        }

        $actions[] = [
            'id' => 'share',
            'icon' => 'share',
            'label' => 'Share'
        ];

        return $actions;
    }

    protected function getEstimatedDelivery(Order $order): ?string
    {
        if (in_array($order->status, ['delivered', 'cancelled'])) {
            return null;
        }

        $estimatedDays = match($order->status) {
            'pending' => 7,
            'processing' => 5,
            'shipped' => 2,
            default => 7
        };

        return now()->addDays($estimatedDays)->format('M d, Y');
    }

    protected function getTrackingInfo(Order $order): ?array
    {
        if (!in_array($order->status, ['shipped', 'delivered'])) {
            return null;
        }

        return [
            'tracking_number' => $order->tracking_number ?? 'TRK' . $order->id,
            'carrier' => 'Standard Delivery',
            'last_update' => $order->updated_at->format('M d, Y g:i A')
        ];
    }

    protected function getOrderActions(Order $order): array
    {
        $actions = [];

        if ($order->can_be_cancelled) {
            $actions[] = [
                'id' => 'cancel',
                'label' => 'Cancel Order',
                'icon' => 'x-circle',
                'destructive' => true
            ];
        }

        if (in_array($order->status, ['delivered'])) {
            $actions[] = [
                'id' => 'return',
                'label' => 'Return Items',
                'icon' => 'arrow-left-circle'
            ];
        }

        $actions[] = [
            'id' => 'support',
            'label' => 'Contact Support',
            'icon' => 'help-circle'
        ];

        return $actions;
    }

    protected function calculateProfileCompletion(User $user): int
    {
        $completionItems = [
            'name' => !empty($user->name),
            'email' => !empty($user->email),
            'phone' => !empty($user->phone),
            'date_of_birth' => !empty($user->date_of_birth),
            'address' => $user->addresses()->count() > 0,
            'avatar' => !empty($user->avatar),
            'preferences' => !empty($user->preferences),
        ];

        $completed = array_sum($completionItems);
        $total = count($completionItems);

        return (int) (($completed / $total) * 100);
    }

    protected function getUserAchievements(User $user): array
    {
        $achievements = [];

        $orderCount = $user->orders()->where('status', 'delivered')->count();
        if ($orderCount >= 10) {
            $achievements[] = [
                'id' => 'frequent_buyer',
                'title' => 'Frequent Buyer',
                'description' => 'Completed 10+ orders',
                'icon' => 'shopping-bag',
                'earned_at' => $user->orders()->where('status', 'delivered')->orderBy('created_at', 'desc')->first()->created_at
            ];
        }

        $reviewCount = $user->reviews()->count();
        if ($reviewCount >= 5) {
            $achievements[] = [
                'id' => 'reviewer',
                'title' => 'Book Reviewer',
                'description' => 'Written 5+ reviews',
                'icon' => 'star',
                'earned_at' => $user->reviews()->orderBy('created_at', 'desc')->first()->created_at
            ];
        }

        return $achievements;
    }
}