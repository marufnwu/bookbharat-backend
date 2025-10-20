<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\GenerateProductAssociations;
use App\Console\Commands\SendAbandonedCartEmails;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule product associations generation daily at 2 AM
Schedule::job(new GenerateProductAssociations(6, 2))
    ->dailyAt('02:00')
    ->name('generate-product-associations')
    ->withoutOverlapping()
    ->onOneServer();

// Abandoned Cart Email Reminders
// First reminder: Every hour for carts abandoned 1 hour ago
Schedule::command('cart:send-abandoned-reminders --type=first')
    ->hourly()
    ->name('abandoned-cart-first-reminder')
    ->withoutOverlapping()
    ->onOneServer();

// Second reminder: Once daily for carts abandoned 24 hours ago
Schedule::command('cart:send-abandoned-reminders --type=second')
    ->dailyAt('10:00')
    ->name('abandoned-cart-second-reminder')
    ->withoutOverlapping()
    ->onOneServer();

// Final reminder: Once daily for carts abandoned 48 hours ago
Schedule::command('cart:send-abandoned-reminders --type=final')
    ->dailyAt('11:00')
    ->name('abandoned-cart-final-reminder')
    ->withoutOverlapping()
    ->onOneServer();

// Review Request: Daily at 9 AM for delivered orders
Schedule::call(function () {
    $deliveredOrders = \App\Models\Order::where('status', 'delivered')
        ->whereBetween('delivered_at', [now()->subDays(7), now()->subDays(3)])
        ->whereDoesntHave('reviews')
        ->with('user')
        ->get();

    foreach ($deliveredOrders as $order) {
        if ($order->user && $order->user->email) {
            $emailService = app(\App\Services\EmailService::class);
            $emailService->sendReviewRequest($order);
        }
    }
})->dailyAt('09:00')
  ->name('send-review-requests')
  ->withoutOverlapping()
  ->onOneServer();
