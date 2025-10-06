<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| Payment Gateway Routes
|--------------------------------------------------------------------------
|
| These routes handle payment gateway callbacks and webhooks.
| They are public routes (no authentication required) as they are called
| by external payment gateways.
|
*/

// Payment gateway callbacks (user redirected back from payment gateway)
Route::get('/payment/{gateway}/callback', [PaymentController::class, 'callback'])->name('payment.callback');

// Payment gateway webhooks (backend notifications from payment gateway)
Route::post('/payment/{gateway}/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
