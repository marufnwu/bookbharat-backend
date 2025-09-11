<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Address;
use App\Services\CustomerAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    protected $analyticsService;

    public function __construct(CustomerAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
        $this->middleware('auth:sanctum');
    }

    public function profile()
    {
        $user = Auth::user()->load([
            'addresses', 
            'customerGroups', 
            'analytics', 
            'loyaltyAccount',
            'socialAccounts'
        ]);

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'preferences' => 'nullable|array',
        ]);

        $user = Auth::user();
        $user->update($request->only([
            'first_name', 'last_name', 'phone', 
            'date_of_birth', 'gender', 'preferences'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    public function addresses()
    {
        $addresses = Auth::user()->addresses()->orderBy('is_default', 'desc')->get();

        return response()->json([
            'success' => true,
            'addresses' => $addresses
        ]);
    }

    public function storeAddress(Request $request)
    {
        $request->validate([
            'type' => 'required|in:home,office,other',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'country' => 'required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $user = Auth::user();

        // If this is set as default, unset other defaults
        if ($request->is_default) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Address added successfully',
            'address' => $address
        ]);
    }

    public function updateAddress(Request $request, Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'type' => 'sometimes|required|in:home,office,other',
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'address_line_1' => 'sometimes|required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'postal_code' => 'sometimes|required|string|max:10',
            'country' => 'sometimes|required|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        // If this is set as default, unset other defaults
        if ($request->is_default) {
            Auth::user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'address' => $address
        ]);
    }

    public function destroyAddress(Address $address)
    {
        if ($address->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }

    public function wishlist()
    {
        $wishlist = Auth::user()->wishlists()->with('product')->latest()->get();

        return response()->json([
            'success' => true,
            'wishlist' => $wishlist
        ]);
    }

    public function addToWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $user = Auth::user();
        
        $existing = $user->wishlists()->where('product_id', $request->product_id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Product already in wishlist'
            ], 400);
        }

        $wishlistItem = $user->wishlists()->create([
            'product_id' => $request->product_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist',
            'wishlist_item' => $wishlistItem->load('product')
        ]);
    }

    public function removeFromWishlist(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $user = Auth::user();
        $removed = $user->wishlists()->where('product_id', $request->product_id)->delete();

        if (!$removed) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in wishlist'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product removed from wishlist'
        ]);
    }

    public function orderHistory(Request $request)
    {
        $query = Auth::user()->orders()->with(['orderItems.product']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')
                        ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function dashboard()
    {
        $user = Auth::user();
        
        $dashboard = [
            'user_stats' => [
                'orders_count' => $user->orders()->count(),
                'completed_orders' => $user->orders()->where('status', 'delivered')->count(),
                'total_spent' => $user->orders()->where('status', 'delivered')->sum('total_amount'),
                'wishlist_count' => $user->wishlists()->count(),
                'loyalty_points' => $user->loyaltyAccount?->points_balance ?? 0,
            ],
            'recent_orders' => $user->orders()
                ->with(['orderItems.product:id,name,primary_image'])
                ->latest()
                ->limit(5)
                ->get(),
            'wishlist_preview' => $user->wishlists()
                ->with('product:id,name,price,primary_image')
                ->latest()
                ->limit(6)
                ->get(),
            'recommendations' => $user->recommendations()
                ->with('product:id,name,price,primary_image,average_rating')
                ->limit(8)
                ->get(),
            'loyalty_summary' => $this->getLoyaltySummary($user),
        ];

        return response()->json([
            'success' => true,
            'dashboard' => $dashboard
        ]);
    }

    public function analytics()
    {
        $user = Auth::user();
        $analytics = $this->analyticsService->getUserAnalytics($user);

        return response()->json([
            'success' => true,
            'analytics' => $analytics
        ]);
    }

    public function preferences()
    {
        return response()->json([
            'success' => true,
            'preferences' => Auth::user()->preferences ?? []
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.notifications' => 'nullable|array',
            'preferences.newsletter' => 'nullable|boolean',
            'preferences.sms_alerts' => 'nullable|boolean',
            'preferences.language' => 'nullable|string|max:10',
            'preferences.currency' => 'nullable|string|max:3',
        ]);

        $user = Auth::user();
        $user->update(['preferences' => $request->preferences]);

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'preferences' => $user->preferences
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'confirmation' => 'required|in:DELETE_MY_ACCOUNT',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect'
            ], 400);
        }

        // Check for active orders
        $activeOrders = $user->orders()->whereIn('status', ['pending', 'processing', 'shipped'])->count();
        if ($activeOrders > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account with active orders'
            ], 400);
        }

        // Anonymize the user data instead of hard delete
        $user->update([
            'name' => 'Deleted User',
            'email' => 'deleted_' . $user->id . '@deleted.com',
            'phone' => null,
            'is_active' => false,
            'deleted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }

    protected function getLoyaltySummary(User $user): array
    {
        $loyaltyAccount = $user->loyaltyAccount;
        
        if (!$loyaltyAccount) {
            return [
                'points_balance' => 0,
                'tier' => 'Bronze',
                'points_to_next_tier' => 1000,
                'recent_activities' => [],
            ];
        }

        return [
            'points_balance' => $loyaltyAccount->points_balance,
            'tier' => $loyaltyAccount->tier,
            'points_earned_this_month' => $user->points()
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('points'),
            'points_to_next_tier' => $loyaltyAccount->getPointsToNextTier(),
            'recent_activities' => $user->points()
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}