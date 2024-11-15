<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\FetchProducts;
use App\Console\Commands\SyncShopifyProducts;
use App\Console\Commands\FetchShopifyData;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


//Fetch required data from Shopify
Schedule::command(FetchShopifyData::class)->dailyAt('13:00');

// Process the queue every 5 minutes with more efficient timings
Schedule::command('queue:work --stop-when-empty --max-time=300 --sleep=3 --tries=3')->everyFiveMinutes();
Schedule::command('queue:restart')->everyFourHours();  // Restart queue workers every 4 hours

// Fetch Products from Rivile every 2 hours
Schedule::command(FetchProducts::class)
        ->everyTwoHours()   // Reduce the frequency to every 2 hours
        ->after(function () {
            // Ensure queued jobs are processed before calling SyncShopifyProducts
            Artisan::call('queue:work --stop-when-empty --max-time=300 --sleep=3 --tries=3');

            // Sync Products to Shopify
            Artisan::call(SyncShopifyProducts::class);
        });