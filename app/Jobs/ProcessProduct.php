<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use App\Services\RivileService;
use Illuminate\Support\Facades\Log;

class ProcessProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $productData;

    /**
     * Create a new job instance.
     */
    public function __construct($productData)
    {
        $this->productData = $productData;
    }

    /**
     * Execute the job.
     */
    public function handle(RivileService $rivileService)
    {
        $product = $this->productData;

        $i33Data = isset($product['I33']) ? (is_array($product['I33']) && array_keys($product['I33']) !== range(0, count($product['I33']) - 1) ? [$product['I33']] : $product['I33']) : [];
        $n37Data = isset($product['N37']) ? (is_array($product['N37']) && array_keys($product['N37']) !== range(0, count($product['N37']) - 1) ? [$product['N37']] : $product['N37']) : [];

        $hasValidData = !empty($i33Data) && !empty($n37Data) &&
            !empty($product['N17_KODAS_PS']) && !empty($product['N17_PAVU']) &&
            !empty($product['N17_KODAS_GS']) && !empty($product['N17_KODAS_LS_4']);

        if ($hasValidData) {
            $description = $rivileService->getDescription($product['N17_KODAS_PS']);
            $productType = $rivileService->getProductType($product['N17_KODAS_GS']);
            $brand = $rivileService->getProductBrand($product['N17_KODAS_LS_4']);
            $collection = $rivileService->getCollectionDetails($product['N17_KODAS_LS_3']);
            $variants = $rivileService->formatVariants($i33Data);
            $image = isset($product['N17_pav_k3']) ? $product['N17_pav_k3'] : null;

            $descriptionValue = $description['lpap']['pap']['value'] ?? '';
            $productTypeValue = $productType['N19']['N19_PAV_K1'] ?? '';
            $brandValue = $brand['N35']['N35_PAV_K1'] ?? '';
            $collectionTitle = $collection['N35']['N35_PAV'] ?? '';
            $collectionDesc = $collection['N35']['N35_PAV_K3'] ?? '';

            $newProductData = [
                'title' => $product['N17_PAVU'],
                'body_html' => $descriptionValue,
                'vendor' => $brandValue,
                'product_type' => $productTypeValue,
                'variants' => json_encode($variants),
                'image' => $image,
                'collection_title' => $collectionTitle,
                'collection_desc' => $collectionDesc,
            ];

            // Check if the product needs to be updated
            $productRecord = Product::where('product_code', $product['N17_KODAS_PS'])->first();

            if (!$productRecord || $this->hasProductChanged($productRecord, $newProductData)) {
                Product::updateOrCreate(
                    ['product_code' => $product['N17_KODAS_PS']],
                    $newProductData
                );
                Log::info('Product saved successfully!', ['product_code' => $product['N17_KODAS_PS']]);
            } else {
                Log::info('Product not updated as no changes detected.', ['product_code' => $product['N17_KODAS_PS']]);
            }
        } else {
            Log::info('Skipping product due to missing critical fields.', ['product_code' => $product['N17_KODAS_PS'] ?? 'Unknown']);
        }
    }

    /**
     * Check if the product data has changed.
     *
     * @param Product $productRecord
     * @param array $newData
     * @return bool
     */
    private function hasProductChanged($productRecord, $newData)
    {
        return $productRecord->title !== $newData['title'] ||
               $productRecord->body_html !== $newData['body_html'] ||
               $productRecord->vendor !== $newData['vendor'] ||
               $productRecord->product_type !== $newData['product_type'] ||
               $productRecord->variants !== $newData['variants'] ||
               $productRecord->collection_title !== $newData['collection_title'];
    }
}