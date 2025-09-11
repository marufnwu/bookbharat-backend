<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\RecommendationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRecommendations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $context;
    protected $attempts = 3;

    public function __construct($userId, $context = 'general')
    {
        $this->userId = $userId;
        $this->context = $context;
    }

    public function handle(RecommendationEngine $recommendationEngine): void
    {
        $user = User::find($this->userId);
        
        if (!$user) {
            Log::warning("Recommendation generation skipped - user not found", [
                'user_id' => $this->userId
            ]);
            return;
        }

        try {
            // Generate recommendations for the user
            $recommendations = $recommendationEngine->generateRecommendations(
                $user,
                $this->context,
                20 // generate more for caching
            );

            Log::info("Recommendations generated successfully", [
                'user_id' => $this->userId,
                'context' => $this->context,
                'recommendations_count' => $recommendations->count()
            ]);

            // Cache recommendations for quick access
            $cacheKey = "user_recommendations:{$this->userId}:{$this->context}";
            cache()->put($cacheKey, $recommendations->toArray(), now()->addHours(6));

            // Generate recommendations for different contexts
            if ($this->context === 'general') {
                $this->generateContextSpecificRecommendations($user, $recommendationEngine);
            }

        } catch (\Exception $e) {
            Log::error("Failed to generate recommendations", [
                'user_id' => $this->userId,
                'context' => $this->context,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function generateContextSpecificRecommendations($user, $recommendationEngine)
    {
        $contexts = ['homepage', 'cart', 'product_page', 'checkout'];

        foreach ($contexts as $context) {
            try {
                $recommendations = $recommendationEngine->generateRecommendations($user, $context, 10);
                $cacheKey = "user_recommendations:{$this->userId}:{$context}";
                cache()->put($cacheKey, $recommendations->toArray(), now()->addHours(6));
                
                Log::debug("Context-specific recommendations cached", [
                    'user_id' => $this->userId,
                    'context' => $context,
                    'count' => $recommendations->count()
                ]);
                
            } catch (\Exception $e) {
                Log::warning("Failed to generate recommendations for context", [
                    'user_id' => $this->userId,
                    'context' => $context,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Recommendation generation job failed", [
            'user_id' => $this->userId,
            'context' => $this->context,
            'error' => $exception->getMessage()
        ]);
    }
}