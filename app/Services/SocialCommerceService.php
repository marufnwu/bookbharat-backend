<?php

namespace App\Services;

use App\Models\User;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Models\UserGeneratedContent;
use App\Models\Order;
use App\Models\ReferralCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialCommerceService
{
    protected $facebookApi = 'https://graph.facebook.com/v17.0/';
    protected $instagramApi = 'https://graph.instagram.com/';

    public function syncSocialAccount(User $user, string $provider, array $socialData)
    {
        $account = SocialAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialData['id']
            ],
            [
                'provider_username' => $socialData['username'] ?? null,
                'access_token' => $socialData['access_token'] ?? null,
                'refresh_token' => $socialData['refresh_token'] ?? null,
                'expires_at' => isset($socialData['expires_in']) ? now()->addSeconds($socialData['expires_in']) : null,
                'profile_data' => [
                    'name' => $socialData['name'] ?? null,
                    'email' => $socialData['email'] ?? null,
                    'avatar' => $socialData['picture'] ?? $socialData['profile_picture_url'] ?? null,
                    'followers_count' => $socialData['followers_count'] ?? 0,
                    'following_count' => $socialData['following_count'] ?? 0,
                ],
                'permissions' => $socialData['permissions'] ?? [],
                'is_active' => true,
            ]
        );

        return $account;
    }

    public function importInstagramContent(User $user, SocialAccount $account)
    {
        if (!$account->access_token || $account->provider !== 'instagram') {
            throw new \Exception('Invalid Instagram account or missing access token');
        }

        try {
            $response = Http::get($this->instagramApi . 'me/media', [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,timestamp,like_count,comments_count',
                'access_token' => $account->access_token,
                'limit' => 50,
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to fetch Instagram media: ' . $response->body());
            }

            $mediaData = $response->json();
            $importedContent = [];

            foreach ($mediaData['data'] as $media) {
                if ($this->containsProductMentions($media['caption'] ?? '')) {
                    $content = UserGeneratedContent::create([
                        'user_id' => $user->id,
                        'content_type' => $media['media_type'] === 'VIDEO' ? 'video' : 'photo',
                        'content' => $media['caption'],
                        'media_files' => [
                            'url' => $media['media_url'],
                            'thumbnail' => $media['thumbnail_url'] ?? null,
                            'type' => $media['media_type']
                        ],
                        'metadata' => [
                            'hashtags' => $this->extractHashtags($media['caption'] ?? ''),
                            'mentions' => $this->extractMentions($media['caption'] ?? ''),
                        ],
                        'source_platform' => 'instagram',
                        'external_id' => $media['id'],
                        'likes_count' => $media['like_count'] ?? 0,
                        'comments_count' => $media['comments_count'] ?? 0,
                        'status' => 'pending',
                        'allow_public_display' => true,
                    ]);

                    $importedContent[] = $content;
                }
            }

            return $importedContent;

        } catch (\Exception $e) {
            Log::error('Instagram content import failed', [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function shareProductToSocial(Product $product, User $user, string $platform, array $options = [])
    {
        $account = $user->socialAccounts()->where('provider', $platform)->where('is_active', true)->first();
        
        if (!$account || !$account->access_token) {
            throw new \Exception("No active {$platform} account found");
        }

        $shareData = $this->prepareProductShareData($product, $options);

        switch ($platform) {
            case 'facebook':
                return $this->shareToFacebook($account, $shareData);
            case 'instagram':
                return $this->shareToInstagram($account, $shareData);
            default:
                throw new \Exception("Unsupported platform: {$platform}");
        }
    }

    public function createReferralCode(User $user, array $options = [])
    {
        $code = $options['custom_code'] ?? $this->generateReferralCode($user);

        return ReferralCode::create([
            'user_id' => $user->id,
            'code' => strtoupper($code),
            'discount_type' => $options['discount_type'] ?? 'percentage',
            'discount_value' => $options['discount_value'] ?? 10,
            'usage_limit' => $options['usage_limit'] ?? null,
            'min_order_amount' => $options['min_order_amount'] ?? 0,
            'expires_at' => $options['expires_at'] ?? now()->addMonths(6),
            'is_active' => true,
            'commission_rate' => $options['commission_rate'] ?? 5.0,
        ]);
    }

    public function trackReferralUsage(string $referralCode, Order $order)
    {
        $referral = ReferralCode::where('code', strtoupper($referralCode))
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$referral) {
            return null;
        }

        if ($referral->usage_limit && $referral->usage_count >= $referral->usage_limit) {
            return null;
        }

        if ($order->total_amount < $referral->min_order_amount) {
            return null;
        }

        $referral->increment('usage_count');
        $referral->increment('total_revenue', $order->total_amount);

        $commissionAmount = ($order->total_amount * $referral->commission_rate) / 100;
        
        $referral->increment('commission_earned', $commissionAmount);

        return [
            'referral' => $referral,
            'commission_earned' => $commissionAmount,
            'discount_applied' => $this->calculateReferralDiscount($referral, $order),
        ];
    }

    public function generateProductShareContent(Product $product, array $options = [])
    {
        $baseUrl = config('app.url');
        $productUrl = "{$baseUrl}/products/{$product->slug}";
        
        if (isset($options['referral_code'])) {
            $productUrl .= "?ref=" . $options['referral_code'];
        }

        $content = [
            'title' => $product->name,
            'description' => $product->short_description ?: Str::limit($product->description, 150),
            'price' => $product->formatted_price,
            'image_url' => $product->primary_image_url,
            'product_url' => $productUrl,
            'hashtags' => $this->generateProductHashtags($product),
            'mention' => '@' . config('app.name'),
        ];

        $template = $options['template'] ?? 'default';
        
        return $this->formatShareContent($content, $template);
    }

    public function moderateUserContent(UserGeneratedContent $content, array $decision)
    {
        $content->update([
            'status' => $decision['status'], // approved, rejected
            'approved_by' => $decision['approved_by'] ?? null,
            'approved_at' => $decision['status'] === 'approved' ? now() : null,
            'is_featured' => $decision['is_featured'] ?? false,
        ]);

        if ($decision['status'] === 'approved' && isset($decision['product_id'])) {
            $content->update(['product_id' => $decision['product_id']]);
        }

        if ($decision['status'] === 'approved' && $content->user) {
            $this->rewardUserForContent($content->user, $content);
        }

        return $content;
    }

    public function getFeaturedContent(array $filters = [], int $limit = 20)
    {
        $query = UserGeneratedContent::with(['user', 'product'])
            ->where('status', 'approved')
            ->where('is_featured', true)
            ->where('allow_public_display', true);

        if (isset($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (isset($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (isset($filters['platform'])) {
            $query->where('source_platform', $filters['platform']);
        }

        return $query->orderBy('likes_count', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    public function getInfluencerMetrics(User $user)
    {
        $socialAccounts = $user->socialAccounts()->where('is_active', true)->get();
        $totalFollowers = $socialAccounts->sum(function ($account) {
            return $account->profile_data['followers_count'] ?? 0;
        });

        $userContent = $user->userGeneratedContent()
            ->where('status', 'approved')
            ->get();

        $totalEngagement = $userContent->sum(function ($content) {
            return $content->likes_count + $content->comments_count + $content->shares_count;
        });

        $referrals = $user->referralCodes()->where('is_active', true)->get();
        $totalCommissions = $referrals->sum('commission_earned');
        $totalReferralRevenue = $referrals->sum('total_revenue');

        return [
            'follower_count' => $totalFollowers,
            'content_pieces' => $userContent->count(),
            'total_engagement' => $totalEngagement,
            'average_engagement' => $userContent->count() > 0 ? $totalEngagement / $userContent->count() : 0,
            'commission_earned' => $totalCommissions,
            'referral_revenue' => $totalReferralRevenue,
            'referral_conversions' => $referrals->sum('usage_count'),
            'influence_score' => $this->calculateInfluenceScore($user, [
                'followers' => $totalFollowers,
                'engagement' => $totalEngagement,
                'conversions' => $referrals->sum('usage_count')
            ]),
        ];
    }

    protected function containsProductMentions(string $content): bool
    {
        $keywords = ['#book', '#bookbharat', '@bookbharat', 'book', 'reading', 'literature'];
        
        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function extractHashtags(string $content): array
    {
        preg_match_all('/#(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    protected function extractMentions(string $content): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    protected function prepareProductShareData(Product $product, array $options): array
    {
        return [
            'message' => $options['message'] ?? $this->generateProductShareContent($product, $options),
            'link' => $options['product_url'] ?? url("/products/{$product->slug}"),
            'picture' => $product->primary_image_url,
            'name' => $product->name,
            'description' => $product->short_description,
        ];
    }

    protected function shareToFacebook(SocialAccount $account, array $shareData)
    {
        try {
            $response = Http::post($this->facebookApi . 'me/feed', array_merge($shareData, [
                'access_token' => $account->access_token,
            ]));

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('Facebook share failed: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Facebook share failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'share_data' => $shareData
            ]);
            throw $e;
        }
    }

    protected function shareToInstagram(SocialAccount $account, array $shareData)
    {
        throw new \Exception('Instagram sharing requires Instagram Business API and is not yet implemented');
    }

    protected function generateReferralCode(User $user): string
    {
        $base = strtoupper(substr($user->name, 0, 3)) . rand(100, 999);
        
        while (ReferralCode::where('code', $base)->exists()) {
            $base = strtoupper(substr($user->name, 0, 3)) . rand(100, 999);
        }

        return $base;
    }

    protected function calculateReferralDiscount(ReferralCode $referral, Order $order): float
    {
        if ($referral->discount_type === 'percentage') {
            return ($order->total_amount * $referral->discount_value) / 100;
        }

        return min($referral->discount_value, $order->total_amount);
    }

    protected function generateProductHashtags(Product $product): array
    {
        $hashtags = ['#BookBharat'];

        if ($product->category) {
            $hashtags[] = '#' . Str::camel($product->category->name);
        }

        if ($product->brand) {
            $hashtags[] = '#' . Str::camel($product->brand);
        }

        $hashtags[] = '#Books';
        $hashtags[] = '#Reading';

        return $hashtags;
    }

    protected function formatShareContent(array $content, string $template): string
    {
        switch ($template) {
            case 'story':
                return "📚 Just discovered this amazing book: {$content['title']}!\n\n{$content['description']}\n\n💰 {$content['price']}\n\n{$content['product_url']}\n\n" . implode(' ', $content['hashtags']);

            case 'review':
                return "⭐ Book Review: {$content['title']}\n\n{$content['description']}\n\nGet yours here: {$content['product_url']}\n\n" . implode(' ', $content['hashtags']);

            default:
                return "Check out this book: {$content['title']} - {$content['price']}\n\n{$content['product_url']}\n\n" . implode(' ', $content['hashtags']);
        }
    }

    protected function rewardUserForContent(User $user, UserGeneratedContent $content)
    {
        $points = match($content->content_type) {
            'photo' => 50,
            'video' => 100,
            'review' => 75,
            'unboxing' => 150,
            default => 25,
        };

        if ($content->is_featured) {
            $points *= 2;
        }

        if ($user->loyaltyAccount) {
            $user->loyaltyAccount->increment('points_balance', $points);
        }
    }

    protected function calculateInfluenceScore(User $user, array $metrics): int
    {
        $followerScore = min(($metrics['followers'] / 1000) * 10, 40);
        $engagementScore = min(($metrics['engagement'] / 100) * 5, 30);
        $conversionScore = min($metrics['conversions'] * 2, 30);
        
        return (int) ($followerScore + $engagementScore + $conversionScore);
    }
}