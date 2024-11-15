<?php

namespace App\Console\Commands;

use App\Jobs\ProcessProduct;
use App\Services\RivileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching products from Rivile API';

    protected $rivileService;

    public function __construct(RivileService $rivileService)
    {
        parent::__construct();
        $this->rivileService = $rivileService;
    }

    /**
     * Execute the console command.
     */
    public function handle(RivileService $rivileService)
    {
        try {
            $this->info('Fetching products from Rivile API...');
            Log::info('Fetching products from Rivile API...');

            $pageNumber = 1;
            $totalProductsFetched = 0;

            do {
                // Fetch product data for the current page
                $response = $rivileService->getProducts($pageNumber);
                $this->info("Products fetched successfully from page $pageNumber.");
                Log::info("Products fetched successfully from page". $pageNumber);

                $products = $response['N17'] ?? [];

                if (!empty($products)) {
                    foreach ($products as $product) {
                        // Dispatch job to process each product
                        ProcessProduct::dispatch($product);
                        $totalProductsFetched++;
                    }
                }

                $pageNumber++;
            } while (!empty($products));

            $this->info("Products sync completed from Rivile API. Total products fetched: $totalProductsFetched.");
            Log::info("Products sync completed from Rivile API. Total products fetched: $totalProductsFetched.");
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('Error fetching products: ' . $e->getMessage());
        }
    }
}
