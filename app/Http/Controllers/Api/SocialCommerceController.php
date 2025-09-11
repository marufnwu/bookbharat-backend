<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SocialCommerceService;
use App\Models\Product;
use App\Models\UserGeneratedContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialCommerceController extends Controller
{
    protected $socialCommerceService;

    public function __construct(SocialCommerceService $socialCommerceService)
    {
        $this->socialCommerceService = $socialCommerceService;
    }

    public function connectSocialAccount(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:facebook,instagram,twitter,google',
            'access_token' => 'required|string',
            'provider_id' => 'required|string',
            'provider_username' => 'nullable|string',
            'profile_data' => 'nullable|array',
        ]);

        try {
            $socialData = $request->only(['access_token', 'provider_id', 'provider_username']) + [
                'id' => $request->provider_id,
                'username' => $request->provider_username,
            ];

            if ($request->profile_data) {
                $socialData = array_merge($socialData, $request->profile_data);
            }

            $account = $this->socialCommerceService->syncSocialAccount(
                Auth::user(),
                $request->provider,
                $socialData
            );

            return response()->json([
                'success' => true,
                'message' => 'Social account connected successfully',
                'account' => $account->makeHidden(['access_token', 'refresh_token'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect social account: ' . $e->getMessage()
            ], 400);
        }
    }

    public function importInstagramContent(Request $request)
    {
        try {
            $user = Auth::user();
            $account = $user->socialAccounts()
                          ->where('provider', 'instagram')
                          ->where('is_active', true)
                          ->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active Instagram account found'
                ], 404);
            }

            $importedContent = $this->socialCommerceService->importInstagramContent($user, $account);

            return response()->json([
                'success' => true,
                'message' => 'Content imported successfully',
                'imported_count' => count($importedContent),
                'content' => $importedContent
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import content: ' . $e->getMessage()
            ], 400);
        }
    }

    public function shareProduct(Request $request, Product $product)
    {
        $request->validate([
            'platform' => 'required|in:facebook,instagram,twitter',
            'message' => 'nullable|string|max:500',
            'template' => 'nullable|in:default,story,review',
            'referral_code' => 'nullable|string',
        ]);

        try {
            $options = $request->only(['message', 'template', 'referral_code']);
            
            $result = $this->socialCommerceService->shareProductToSocial(
                $product,
                Auth::user(),
                $request->platform,
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Product shared successfully',
                'share_result' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to share product: ' . $e->getMessage()
            ], 400);
        }
    }

    public function generateShareContent(Request $request, Product $product)
    {
        $request->validate([
            'template' => 'nullable|in:default,story,review',
            'referral_code' => 'nullable|string',
        ]);

        try {
            $options = $request->only(['template', 'referral_code']);
            
            $content = $this->socialCommerceService->generateProductShareContent($product, $options);

            return response()->json([
                'success' => true,
                'share_content' => $content
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate share content: ' . $e->getMessage()
            ], 400);
        }
    }

    public function createReferralCode(Request $request)
    {
        $request->validate([
            'custom_code' => 'nullable|string|min:3|max:20|unique:referral_codes,code',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'min_order_amount' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after:now',
            'commission_rate' => 'nullable|numeric|min:0|max:50',
        ]);

        try {
            $options = $request->only([
                'custom_code', 'discount_type', 'discount_value', 'usage_limit',
                'min_order_amount', 'expires_at', 'commission_rate'
            ]);

            $referralCode = $this->socialCommerceService->createReferralCode(Auth::user(), $options);

            return response()->json([
                'success' => true,
                'message' => 'Referral code created successfully',
                'referral_code' => $referralCode
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create referral code: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getUserReferrals()
    {
        $user = Auth::user();
        
        $referrals = $user->referralCodes()
                         ->with(['usages.order.user'])
                         ->orderBy('created_at', 'desc')
                         ->get();

        return response()->json([
            'success' => true,
            'referrals' => $referrals,
            'summary' => [
                'total_codes' => $referrals->count(),
                'active_codes' => $referrals->where('is_active', true)->count(),
                'total_usage' => $referrals->sum('usage_count'),
                'total_commission' => $referrals->sum('commission_earned'),
                'total_revenue' => $referrals->sum('total_revenue'),
            ]
        ]);
    }

    public function getFeaturedContent(Request $request)
    {
        $request->validate([
            'content_type' => 'nullable|in:photo,video,review,story,unboxing',
            'product_id' => 'nullable|exists:products,id',
            'platform' => 'nullable|in:instagram,facebook,website',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $filters = $request->only(['content_type', 'product_id', 'platform']);
        $limit = $request->input('limit', 20);

        $content = $this->socialCommerceService->getFeaturedContent($filters, $limit);

        return response()->json([
            'success' => true,
            'content' => $content,
            'count' => $content->count()
        ]);
    }

    public function submitUserContent(Request $request)
    {
        $request->validate([
            'content_type' => 'required|in:photo,video,review,story,unboxing',
            'content' => 'nullable|string',
            'media_files' => 'nullable|array',
            'media_files.*.url' => 'required_with:media_files|url',
            'media_files.*.type' => 'required_with:media_files|in:image,video',
            'product_id' => 'nullable|exists:products,id',
            'rating' => 'nullable|numeric|min:1|max:5',
            'hashtags' => 'nullable|array',
            'mentions' => 'nullable|array',
        ]);

        try {
            $metadata = [];
            if ($request->hashtags) {
                $metadata['hashtags'] = $request->hashtags;
            }
            if ($request->mentions) {
                $metadata['mentions'] = $request->mentions;
            }

            $content = UserGeneratedContent::create([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'content_type' => $request->content_type,
                'content' => $request->content,
                'media_files' => $request->media_files,
                'metadata' => $metadata,
                'rating' => $request->rating,
                'source_platform' => 'website',
                'status' => 'pending',
                'allow_public_display' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Content submitted successfully and is pending approval',
                'content' => $content
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit content: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getUserContent(Request $request)
    {
        $user = Auth::user();
        
        $query = $user->userGeneratedContent()->with('product');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->content_type) {
            $query->where('content_type', $request->content_type);
        }

        $content = $query->orderBy('created_at', 'desc')
                        ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'content' => $content
        ]);
    }

    public function getInfluencerMetrics()
    {
        try {
            $user = Auth::user();
            $metrics = $this->socialCommerceService->getInfluencerMetrics($user);

            return response()->json([
                'success' => true,
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get metrics: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getSocialAccounts()
    {
        $user = Auth::user();
        $accounts = $user->socialAccounts()
                        ->select(['id', 'provider', 'provider_username', 'profile_data', 'is_active', 'created_at'])
                        ->get();

        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }

    public function disconnectSocialAccount(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:facebook,instagram,twitter,google',
        ]);

        $user = Auth::user();
        $account = $user->socialAccounts()
                       ->where('provider', $request->provider)
                       ->first();

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Social account not found'
            ], 404);
        }

        $account->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Social account disconnected successfully'
        ]);
    }
}