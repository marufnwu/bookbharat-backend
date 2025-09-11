<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CustomerSegmentationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateCustomerAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $attempts = 3;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function handle(CustomerSegmentationService $segmentationService): void
    {
        $user = User::find($this->userId);
        
        if (!$user) {
            Log::warning("Customer analytics update skipped - user not found", [
                'user_id' => $this->userId
            ]);
            return;
        }

        try {
            // Calculate customer analytics
            $analytics = $segmentationService->calculateCustomerAnalytics($user);
            
            // Assign user to appropriate segments
            $segments = $segmentationService->assignUserToSegments($user);
            
            Log::info("Customer analytics updated successfully", [
                'user_id' => $this->userId,
                'lifetime_value' => $analytics->lifetime_value,
                'customer_segment' => $analytics->customer_segment,
                'lifecycle_stage' => $analytics->lifecycle_stage,
                'assigned_segments' => count($segments)
            ]);

            // Update customer groups based on analytics
            $this->updateCustomerGroups($user, $analytics);

        } catch (\Exception $e) {
            Log::error("Failed to update customer analytics", [
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function updateCustomerGroups($user, $analytics)
    {
        $groupsToAssign = [];

        // Auto-assign to VIP group
        if ($analytics->customer_segment === 'vip') {
            $vipGroup = \App\Models\CustomerGroup::where('slug', 'vip')->first();
            if ($vipGroup) {
                $groupsToAssign[] = $vipGroup->id;
            }
        }

        // Auto-assign to regular customers group
        if ($analytics->customer_segment === 'regular') {
            $regularGroup = \App\Models\CustomerGroup::where('slug', 'regular')->first();
            if ($regularGroup) {
                $groupsToAssign[] = $regularGroup->id;
            }
        }

        // Auto-assign based on lifecycle stage
        if ($analytics->lifecycle_stage === 'at_risk') {
            $atRiskGroup = \App\Models\CustomerGroup::where('slug', 'at-risk')->first();
            if ($atRiskGroup) {
                $groupsToAssign[] = $atRiskGroup->id;
            }
        }

        if (!empty($groupsToAssign)) {
            $user->customerGroups()->syncWithoutDetaching($groupsToAssign);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Customer analytics update job failed", [
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);
    }
}