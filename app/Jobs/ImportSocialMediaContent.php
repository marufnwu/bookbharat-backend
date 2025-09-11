<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\SocialAccount;
use App\Services\SocialCommerceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportSocialMediaContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $accountId;

    public function __construct(int $userId, int $accountId)
    {
        $this->userId = $userId;
        $this->accountId = $accountId;
    }

    public function handle(SocialCommerceService $socialCommerceService)
    {
        try {
            $user = User::findOrFail($this->userId);
            $account = SocialAccount::findOrFail($this->accountId);
            
            if ($account->user_id !== $user->id) {
                throw new \Exception('Social account does not belong to user');
            }

            if ($account->provider === 'instagram') {
                $importedContent = $socialCommerceService->importInstagramContent($user, $account);
                
                Log::info('Social media content imported successfully', [
                    'user_id' => $this->userId,
                    'account_id' => $this->accountId,
                    'provider' => $account->provider,
                    'imported_count' => count($importedContent)
                ]);

                // Notify user of successful import
                $user->notify(new \App\Notifications\SocialContentImportedNotification(
                    $account->provider,
                    count($importedContent)
                ));

            } else {
                Log::warning('Unsupported provider for content import', [
                    'provider' => $account->provider,
                    'account_id' => $this->accountId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to import social media content', [
                'user_id' => $this->userId,
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}