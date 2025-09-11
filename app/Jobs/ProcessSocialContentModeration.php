<?php

namespace App\Jobs;

use App\Models\UserGeneratedContent;
use App\Services\SocialCommerceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSocialContentModeration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contentId;
    protected $moderationAction;

    public function __construct(int $contentId, array $moderationAction)
    {
        $this->contentId = $contentId;
        $this->moderationAction = $moderationAction;
    }

    public function handle(SocialCommerceService $socialCommerceService)
    {
        try {
            $content = UserGeneratedContent::findOrFail($this->contentId);
            
            $result = $socialCommerceService->moderateUserContent($content, $this->moderationAction);
            
            if ($result->status === 'approved' && $result->user) {
                $this->notifyUserOfApproval($result);
                $this->updateUserInfluenceScore($result->user);
            }

            Log::info('Social content moderation processed', [
                'content_id' => $this->contentId,
                'status' => $result->status,
                'user_id' => $result->user_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process social content moderation', [
                'content_id' => $this->contentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    protected function notifyUserOfApproval(UserGeneratedContent $content)
    {
        // Send notification to user about approval
        $content->user->notify(new \App\Notifications\ContentApprovedNotification($content));
    }

    protected function updateUserInfluenceScore($user)
    {
        // Update user's influence score based on approved content
        $approvedContent = $user->userGeneratedContent()
                               ->where('status', 'approved')
                               ->count();
        
        $featuredContent = $user->userGeneratedContent()
                               ->where('status', 'approved')
                               ->where('is_featured', true)
                               ->count();

        // Simple scoring algorithm
        $influenceScore = ($approvedContent * 10) + ($featuredContent * 25);
        
        // Update user's analytics or profile with influence score
        if ($user->analytics) {
            $user->analytics->update(['influence_score' => $influenceScore]);
        }
    }
}