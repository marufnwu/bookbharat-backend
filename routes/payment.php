<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Routes
|--------------------------------------------------------------------------
|
| Payment gateway routes are now handled in api.php
| This file is kept for potential future payment-specific route requirements
|
*/

// All payment routes are now centralized in api.php under the /api/v1/payment prefix
// This provides a unified payment gateway system with:
// - Gateway callbacks: /api/v1/payment/callback/{gateway}
// - Webhooks: /api/v1/payment/webhook/{gateway}
// - Payment status: /api/v1/payment/status/{orderId}
// - Available gateways: /api/v1/payment/gateways