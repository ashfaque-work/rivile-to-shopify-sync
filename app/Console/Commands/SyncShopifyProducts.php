<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ShopifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncShopifyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-shopify-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        try {
            $this->info('Starting product sync to Shopify...');
            Log::info('Starting product sync to Shopify...');

            // Get the current time and calculate the threshold time
            // $timeAgo = now()->subMinutes(10); //(10 minutes ago)
            $timeAgo = now()->subHours(12); //(12 hours ago)

            // Retrieve all products from the database
            $products = Product::where(function ($query) use ($timeAgo) {
                $query->whereNull('shopify_product_id') // Products that need to be created on Shopify
                      ->orWhere(function ($subQuery) use ($timeAgo) {
                          $subQuery->whereNotNull('shopify_product_id') // Products already on Shopify
                                   ->where('updated_at', '<', $timeAgo); // And need an update
                      });
            })->get();
            
            foreach ($products as $product) {
                try {
                    $imagePath = $product->image ?? '';
                    $imageUrl = str_replace(['\\\\', '\\'], ['/', '/'], $imagePath); // Replace backslashes with forward slashes                   

                    // Prepare data for Shopify
                    $shopifyProductData = [
                        'title' => $product->title ?? 'No Title',
                        'body_html' => $product->body_html ?? '',
                        'vendor' => $product->vendor ?? 'No Vendor',
                        'product_type' => $product->product_type ?? 'No Type',
                        'variants' => json_decode($product->variants, true) ?? [],
                        'collection_title' => $product->collection_title ?? 'No Collection Title',
                        'collection_desc' => $product->collection_desc ?? 'No Collection Desc',
                        'image' => $imageUrl ?? '',
                    ];

                    // Check if the variants or images are properly formed arrays
                    if (!is_array($shopifyProductData['variants'])) {
                        $shopifyProductData['variants'] = [];
                        Log::warning('Variants are not in the expected format, initializing to empty array.', ['product_code' => $product->product_code]);
                    }

                    if ($product->shopify_product_id) {
                        // Update existing product
                        try {
                            $this->shopifyService->updateProduct($product->shopify_product_id, $shopifyProductData);
                            
                            $product->touch();
                            Log::info("Updated product in Shopify: {$product->product_code}");
                        } catch (\Exception $e) {
                            $this->error("Failed to update product in Shopify: {$product->product_code} - " . $e->getMessage());
                            Log::error("Failed to update product in Shopify: {$product->product_code} - " . $e->getMessage());
                        }
                    } else {
                        // Create a new product
                        try {
                            $response = $this->shopifyService->createProduct($shopifyProductData);
                            // Log::info('Product Create in Shopify.', ['response' => $response]);

                            $shopifyProductId = $response['id'] ?? null;
                            if ($shopifyProductId) {
                                $product->shopify_product_id = $shopifyProductId;

                                // Check and manage collection assignment
                                if (empty($product->shopify_collection_id)) {
                                    $collectionId = $this->shopifyService->getOrCreateCollection(
                                        $product->collection_title, $product->collection_desc
                                    );

                                    // Add product to the collection
                                    $this->shopifyService->addProductToCollection($collectionId, $shopifyProductId);
                                    
                                    // Update product with the collection ID
                                    $product->shopify_collection_id = $collectionId;
                                    Log::info("Assigned product to collection: {$collectionId}");
                                    
                                    //Publish product to channels
                                    $this->shopifyService->publishProduct($shopifyProductId);
                                    Log::info("Product published to channels: {$shopifyProductId}");                                  
                                }

                                $product->save();

                                Log::info("Created product in Shopify: {$product->product_code}");
                            } else {
                                throw new \Exception('No product ID returned from Shopify.');
                            }
                        } catch (\Exception $e) {
                            Log::error("Error creating product in Shopify:" . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Error syncing product: {$product->product_code} - " . $e->getMessage());
                }
            }

            $this->info('Product sync to Shopify completed.');
            Log::info('Product sync to Shopify completed.');
        } catch (\Exception $e) {
            Log::error('Error syncing products to Shopify: ' . $e->getMessage());
        }
    }
}
