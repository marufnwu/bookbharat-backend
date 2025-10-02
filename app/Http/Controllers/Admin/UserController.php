<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerGroup;
use App\Services\CustomerAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\CustomNotificationMail;

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

    /**
     * Bulk actions on users
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete,assign_group,remove_group,send_email',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'customer_group_id' => 'required_if:action,assign_group,remove_group|exists:customer_groups,id',
            'email_subject' => 'required_if:action,send_email|string|max:255',
            'email_message' => 'required_if:action,send_email|string',
        ]);

        $users = User::whereIn('id', $request->user_ids)->get();
        $action = $request->action;
        $successCount = 0;
        $failedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($users as $user) {
                try {
                    switch ($action) {
                        case 'activate':
                            $user->update(['is_active' => true]);
                            $successCount++;
                            break;

                        case 'deactivate':
                            $user->update(['is_active' => false]);
                            $successCount++;
                            break;

                        case 'delete':
                            // Soft delete or mark as deleted
                            $user->update(['is_active' => false]);
                            // $user->delete(); // Uncomment if soft delete is enabled
                            $successCount++;
                            break;

                        case 'assign_group':
                            if (!$user->customerGroups->contains($request->customer_group_id)) {
                                $user->customerGroups()->attach($request->customer_group_id);
                            }
                            $successCount++;
                            break;

                        case 'remove_group':
                            $user->customerGroups()->detach($request->customer_group_id);
                            $successCount++;
                            break;

                        case 'send_email':
                            try {
                                Mail::raw($request->email_message, function ($mail) use ($user, $request) {
                                    $mail->to($user->email)
                                        ->subject($request->email_subject);
                                });
                                $successCount++;
                            } catch (\Exception $e) {
                                Log::error('Failed to send bulk email', [
                                    'user_id' => $user->id,
                                    'error' => $e->getMessage()
                                ]);
                                $failedCount++;
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    Log::error('Bulk action failed for user', [
                        'user_id' => $user->id,
                        'action' => $action,
                        'error' => $e->getMessage()
                    ]);
                    $failedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk action '{$action}' completed",
                'stats' => [
                    'total' => count($request->user_ids),
                    'successful' => $successCount,
                    'failed' => $failedCount,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk action failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email to specific user
     */
    public function sendEmail(Request $request, User $user)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'template' => 'nullable|string',
        ]);

        try {
            Mail::raw($request->message, function ($mail) use ($user, $request) {
                $mail->to($user->email)
                    ->subject($request->subject);
            });

            // Log the email send
            activity()
                ->causedBy($request->user())
                ->performedOn($user)
                ->withProperties([
                    'subject' => $request->subject,
                    'template' => $request->input('template', 'custom'),
                ])
                ->log('Email sent to user');

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully to ' . $user->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email to user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export users to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = User::with(['customerGroups'])
                ->withCount(['orders', 'reviews'])
                ->withSum(['orders as total_spent' => function ($q) {
                    $q->where('status', 'delivered');
                }], 'total_amount');

            // Apply same filters as index
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

            $users = $query->orderBy('created_at', 'desc')->get();

            // Generate CSV content
            $csvContent = "ID,Name,Email,Phone,Status,Total Orders,Total Spent,Customer Groups,Registered At\n";

            foreach ($users as $user) {
                $groups = $user->customerGroups->pluck('name')->implode('|');
                $csvContent .= sprintf(
                    "%d,\"%s\",\"%s\",\"%s\",\"%s\",%d,%.2f,\"%s\",\"%s\"\n",
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone ?? '',
                    $user->is_active ? 'Active' : 'Inactive',
                    $user->orders_count ?? 0,
                    $user->total_spent ?? 0,
                    $groups,
                    $user->created_at->format('Y-m-d H:i:s')
                );
            }

            $filename = 'users_export_' . now()->format('Y-m-d_His') . '.csv';

            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            Log::error('User export failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Impersonate user (for admin debugging)
     */
    public function impersonate(Request $request, User $user)
    {
        try {
            // Prevent impersonating other admins
            if ($user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot impersonate admin users'
                ], 403);
            }

            // Create impersonation token
            $abilities = $user->getRoleNames()->toArray();
            if (empty($abilities)) {
                $abilities = ['customer'];
            }

            $token = $user->createToken('impersonation_token', $abilities)->plainTextToken;

            // Log impersonation
            activity()
                ->causedBy($request->user())
                ->performedOn($user)
                ->withProperties([
                    'admin_id' => $request->user()->id,
                    'admin_email' => $request->user()->email,
                ])
                ->log('Admin impersonated user');

            return response()->json([
                'success' => true,
                'message' => 'Impersonation token generated',
                'data' => [
                    'user' => $user->load('roles'),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'impersonating' => true,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to impersonate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function destroy(User $user)
    {
        try {
            // Prevent deleting users with active orders
            $hasActiveOrders = $user->orders()
                ->whereIn('status', ['pending', 'processing', 'shipped'])
                ->exists();

            if ($hasActiveOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete user with active orders. Please complete or cancel orders first.'
                ], 400);
            }

            // Deactivate instead of hard delete
            $user->update([
                'is_active' => false,
                'email' => 'deleted_' . $user->id . '_' . $user->email,
            ]);

            activity()
                ->causedBy(request()->user())
                ->performedOn($user)
                ->log('User deleted/deactivated');

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}