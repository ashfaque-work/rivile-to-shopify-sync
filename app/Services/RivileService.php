<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RivileService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.rivile.url');
        $this->apiKey = config('services.rivile.key');
    }

    public function getProducts($pageNumber = 1)
    {
        $params = [
            'list' => config('services.rivile.list'),
            'pagenumber' => $pageNumber
        ];
        return $this->revileAPI($params, config('services.rivile.product_list_method'));
    }

    public function getDescription($kodas)
    {
        $params = [
            'forma' => "PSN17",
            'kodas1' => $kodas,
            'kuris' => 3,
        ];
        return $this->revileAPI($params, config('services.rivile.description_method'));
    }

    public function getProductBrand($brandCode)
    {
        $params = [
            'fil' => "n35_kodas_ls='$brandCode'"
        ];
        return $this->revileAPI($params, config('services.rivile.product_brand_method'));
    }

    public function getProductType($kodasGs)
    {
        $params = [
            'fil' => "n19_kodas_gs='$kodasGs'"
        ];
        return $this->revileAPI($params, config('services.rivile.product_group_method'));
    }

    public function getCollectionDetails($collectionCode)
    {
        $params = [
            'fil' => "n35_kodas_ls='$collectionCode'"
        ];
        return $this->revileAPI($params, config('services.rivile.collection_method'));
    }

    public function formatVariants($i33Data)
    {
        $variants = [];

        // Ensure $i33Data is an array, even if it's a single object
        if (!is_array($i33Data)) {
            $i33Data = [$i33Data];
        }

        foreach ($i33Data as $variant) {
            if (is_array($variant)) {
                $inventoryQuantity = $this->getInventoryQuantity($variant['I33_KODAS_PS'], $variant['I33_KODAS_IS']);
                $variants[] = [
                    'option1' => $variant['I33_KODAS_US'] ?? 'Unknown',
                    'price' => $variant['I33_KAINA'] ?? '0.00',
                    'sku' => $variant['I33_KODAS_PS'] ?? '',
                    'inventory_management' => 'shopify',
                    'inventory_quantity' => intval($inventoryQuantity['I17']['likutis_us_a'] ?? 0),
                ];
            } else {
                Log::warning('Unexpected variant format', ['variant' => $variant]);
            }
        }

        return $variants;
    }

    protected function revileAPI($params, $method)
    {
        $response = Http::withHeaders([
            'ApiKey' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => "application/json",
        ])->post($this->apiUrl, [
            'method' => $method,
            'params' => $params,
        ]);

        // Check for a successful response
        if ($response->successful()) {
            return $response->json();
        }

        // Handle errors (optional)
        throw new \Exception("Failed to fetch products from Rivile: " . $response->body());
    }

    public function getInventoryQuantity($kodas_ps, $kodas_is)
    {
        $params = [
            'fil' => "i17_kodas_ps='$kodas_ps' and i17_kodas_is=$kodas_is"
        ];
        return $this->revileAPI($params, config('services.rivile.inventory_method'));
    }
}
