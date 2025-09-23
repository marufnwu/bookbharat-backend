<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerGroup;
use App\Services\CustomerAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    protected $analyticsService;

    public function __construct(CustomerAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
        // Simplified for now - admin role check is handled at route level
    }

    public function index(Request $request)
    {
        $query = User::with(['customerGroups'])
            ->withCount(['orders', 'reviews'])
            ->withSum(['orders as total_spent' => function ($q) {
                $q->where('status', 'delivered');
            }], 'total_amount');

        if ($request->status) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->customer_group) {
            $query->whereHas('customerGroups', function ($q) use ($request) {
                $q->where('customer_groups.id', $request->customer_group);
            });
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        $users = $query->orderBy('created_at', 'desc')
                      ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'users' => $users,
            'stats' => $this->getUserStats(),
            'filters' => $this->getUserFilters()
        ]);
    }

    public function show(User $user)
    {
        $user->load([
            'addresses',
            'orders.orderItems.product:id,name',
            'reviews.product:id,name',
            'customerGroups',
            // 'analytics', // Disabled - needs proper migration
            // 'loyaltyAccount', // Disabled - model doesn't exist
            // 'referralCodes', // Disabled - model doesn't exist
            // 'socialAccounts' // Disabled - model doesn't exist
        ]);

        $analytics = $this->analyticsService->getUserAnalytics($user);

        return response()->json([
            'success' => true,
            'user' => $user,
            'analytics' => $analytics
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'customer_group_ids' => 'nullable|array',
            'customer_group_ids.*' => 'exists:customer_groups,id',
            'notes' => 'nullable|string',
        ]);

        $user->update($request->only(['name', 'email', 'phone', 'is_active', 'notes']));

        if ($request->has('customer_group_ids')) {
            $user->customerGroups()->sync($request->customer_group_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user->load('customerGroups')
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'User activated' : 'User deactivated',
            'is_active' => $user->is_active
        ]);
    }

    protected function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'new_users_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'users_with_orders' => User::whereHas('orders')->count(),
            'average_order_value' => DB::table('orders')
                ->where('status', 'delivered')
                ->whereNotNull('user_id')
                ->avg('total_amount') ?? 0,
        ];
    }

    protected function getUserFilters(): array
    {
        return [
            'customer_groups' => CustomerGroup::select('id', 'name')->get(),
            'statuses' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ]
        ];
    }

    public function getAnalytics(User $user)
    {
        $analytics = $this->analyticsService->getUserAnalytics($user);

        return response()->json([
            'success' => true,
            'analytics' => $analytics
        ]);
    }

    public function getOrders(User $user)
    {
        $orders = $user->orders()
            ->with(['orderItems.product:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'orders' => $orders
        ]);
    }

    public function getAddresses(User $user)
    {
        $addresses = $user->addresses;

        return response()->json([
            'success' => true,
            'addresses' => $addresses
        ]);
    }
}