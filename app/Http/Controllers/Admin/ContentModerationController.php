<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserGeneratedContent;
use App\Services\SocialCommerceService;
use App\Jobs\ProcessSocialContentModeration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentModerationController extends Controller
{
    protected $socialCommerceService;

    public function __construct(SocialCommerceService $socialCommerceService)
    {
        $this->socialCommerceService = $socialCommerceService;
        $this->middleware('permission:moderate-content');
    }

    public function index(Request $request)
    {
        $query = UserGeneratedContent::with(['user', 'product'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->content_type) {
            $query->where('content_type', $request->content_type);
        }

        if ($request->platform) {
            $query->where('source_platform', $request->platform);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('content', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function ($userQuery) use ($request) {
                      $userQuery->where('name', 'like', '%' . $request->search . '%')
                               ->orWhere('email', 'like', '%' . $request->search . '%');
                  });
            });
        }

        $content = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'content' => $content,
            'stats' => $this->getModerationStats()
        ]);
    }

    public function show(UserGeneratedContent $content)
    {
        $content->load(['user', 'product', 'approver']);

        return response()->json([
            'success' => true,
            'content' => $content
        ]);
    }

    public function approve(Request $request, UserGeneratedContent $content)
    {
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'is_featured' => 'nullable|boolean',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $moderationAction = [
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'product_id' => $request->product_id,
            'is_featured' => $request->input('is_featured', false),
            'admin_notes' => $request->admin_notes,
        ];

        ProcessSocialContentModeration::dispatch($content->id, $moderationAction);

        return response()->json([
            'success' => true,
            'message' => 'Content approval is being processed'
        ]);
    }

    public function reject(Request $request, UserGeneratedContent $content)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $moderationAction = [
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'rejection_reason' => $request->rejection_reason,
            'admin_notes' => $request->admin_notes,
        ];

        ProcessSocialContentModeration::dispatch($content->id, $moderationAction);

        return response()->json([
            'success' => true,
            'message' => 'Content rejection is being processed'
        ]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,reject,feature,unfeature',
            'content_ids' => 'required|array|min:1',
            'content_ids.*' => 'exists:user_generated_content,id',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
            'product_id' => 'nullable|exists:products,id',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $contentIds = $request->content_ids;
        $action = $request->action;

        foreach ($contentIds as $contentId) {
            $moderationAction = [
                'approved_by' => Auth::id(),
                'admin_notes' => $request->admin_notes,
            ];

            switch ($action) {
                case 'approve':
                    $moderationAction['status'] = 'approved';
                    if ($request->product_id) {
                        $moderationAction['product_id'] = $request->product_id;
                    }
                    break;

                case 'reject':
                    $moderationAction['status'] = 'rejected';
                    $moderationAction['rejection_reason'] = $request->rejection_reason;
                    break;

                case 'feature':
                    $moderationAction['is_featured'] = true;
                    break;

                case 'unfeature':
                    $moderationAction['is_featured'] = false;
                    break;
            }

            ProcessSocialContentModeration::dispatch($contentId, $moderationAction);
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk {$action} action is being processed for " . count($contentIds) . " items"
        ]);
    }

    public function getFeatured(Request $request)
    {
        $featured = $this->socialCommerceService->getFeaturedContent(
            $request->only(['content_type', 'product_id', 'platform']),
            $request->input('limit', 50)
        );

        return response()->json([
            'success' => true,
            'featured_content' => $featured
        ]);
    }

    public function updateFeaturedStatus(Request $request, UserGeneratedContent $content)
    {
        $request->validate([
            'is_featured' => 'required|boolean',
        ]);

        $content->update([
            'is_featured' => $request->is_featured,
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->is_featured ? 'Content featured successfully' : 'Content unfeatured successfully',
            'content' => $content
        ]);
    }

    public function getContentAnalytics()
    {
        $analytics = [
            'total_content' => UserGeneratedContent::count(),
            'pending_review' => UserGeneratedContent::where('status', 'pending')->count(),
            'approved_content' => UserGeneratedContent::where('status', 'approved')->count(),
            'rejected_content' => UserGeneratedContent::where('status', 'rejected')->count(),
            'featured_content' => UserGeneratedContent::where('is_featured', true)->count(),
            'content_by_type' => UserGeneratedContent::selectRaw('content_type, count(*) as count')
                ->groupBy('content_type')
                ->get()
                ->pluck('count', 'content_type'),
            'content_by_platform' => UserGeneratedContent::selectRaw('source_platform, count(*) as count')
                ->groupBy('source_platform')
                ->get()
                ->pluck('count', 'source_platform'),
            'top_contributors' => UserGeneratedContent::with('user')
                ->selectRaw('user_id, count(*) as content_count, sum(likes_count + comments_count + shares_count) as total_engagement')
                ->where('status', 'approved')
                ->groupBy('user_id')
                ->orderBy('content_count', 'desc')
                ->limit(10)
                ->get(),
            'engagement_stats' => [
                'total_likes' => UserGeneratedContent::where('status', 'approved')->sum('likes_count'),
                'total_comments' => UserGeneratedContent::where('status', 'approved')->sum('comments_count'),
                'total_shares' => UserGeneratedContent::where('status', 'approved')->sum('shares_count'),
            ]
        ];

        return response()->json([
            'success' => true,
            'analytics' => $analytics
        ]);
    }

    protected function getModerationStats()
    {
        return [
            'pending_count' => UserGeneratedContent::where('status', 'pending')->count(),
            'approved_today' => UserGeneratedContent::where('status', 'approved')
                ->whereDate('approved_at', today())->count(),
            'rejected_today' => UserGeneratedContent::where('status', 'rejected')
                ->whereDate('updated_at', today())->count(),
            'featured_count' => UserGeneratedContent::where('is_featured', true)->count(),
        ];
    }
}