<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\GenerateProductAssociations;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule product associations generation daily at 2 AM
Schedule::job(new GenerateProductAssociations(6, 2))
    ->dailyAt('02:00')
    ->name('generate-product-associations')
    ->withoutOverlapping()
    ->onOneServer();
