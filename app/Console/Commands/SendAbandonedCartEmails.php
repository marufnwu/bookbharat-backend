<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PersistentCart;
use App\Jobs\SendAbandonedCartEmail;
use Illuminate\Support\Facades\Log;

class SendAbandonedCartEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cart:send-abandoned-reminders {--type=all : Type of reminder (first|second|final|all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send abandoned cart recovery emails based on cart age';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $sent = 0;

        $this->info('Processing abandoned cart reminders...');

        if ($type === 'all' || $type === 'first') {
            $sent += $this->sendFirstReminders();
        }

        if ($type === 'all' || $type === 'second') {
            $sent += $this->sendSecondReminders();
        }

        if ($type === 'all' || $type === 'final') {
            $sent += $this->sendFinalReminders();
        }

        $this->info("Sent {$sent} abandoned cart email(s)");
        Log::info('Abandoned cart emails processed', ['sent' => $sent, 'type' => $type]);

        return 0;
    }

    /**
     * Send first reminders (1 hour after abandonment)
     */
    protected function sendFirstReminders()
    {
        $carts = PersistentCart::with('user')
            ->where('is_abandoned', true)
            ->where('last_activity', '>', now()->subHours(25))
            ->where('last_activity', '<=', now()->subHour())
            ->where(function($query) {
                $query->whereNull('recovery_email_count')
                      ->orWhere('recovery_email_count', 0);
            })
            ->get();

        return $this->dispatchEmails($carts, 'first_reminder');
    }

    /**
     * Send second reminders (24 hours after abandonment)
     */
    protected function sendSecondReminders()
    {
        $carts = PersistentCart::with('user')
            ->where('is_abandoned', true)
            ->where('last_activity', '>', now()->subHours(49))
            ->where('last_activity', '<=', now()->subHours(24))
            ->where('recovery_email_count', 1)
            ->get();

        return $this->dispatchEmails($carts, 'second_reminder');
    }

    /**
     * Send final reminders (48 hours after abandonment)
     */
    protected function sendFinalReminders()
    {
        $carts = PersistentCart::with('user')
            ->where('is_abandoned', true)
            ->where('last_activity', '<=', now()->subHours(48))
            ->where('recovery_email_count', 2)
            ->get();

        return $this->dispatchEmails($carts, 'final_reminder');
    }

    /**
     * Dispatch email jobs
     */
    protected function dispatchEmails($carts, $emailType)
    {
        $count = 0;

        foreach ($carts as $cart) {
            if ($cart->user && $cart->user->email) {
                SendAbandonedCartEmail::dispatch($cart->id, $emailType);
                $count++;
                $this->line("Queued {$emailType} for cart #{$cart->id} (User: {$cart->user->email})");
            }
        }

        return $count;
    }
}

