<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;

class FetchShopifyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-shopify-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Shopify locations and publications data and store it into the database';

    protected $shopifyService;

    public function __construct(ShopifyService $shopifyService)
    {
        parent::__construct();
        $this->shopifyService = $shopifyService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching Shopify data...');

        // Fetch locations and publications
        $this->shopifyService->getLocations();
        $this->shopifyService->getPublications();

        $this->info('Shopify data fetch completed.');
    }
}
