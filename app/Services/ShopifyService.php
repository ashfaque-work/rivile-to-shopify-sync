<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Publication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected $shopUrl;
    protected $accessToken;

    public function __construct()
    {
        $this->shopUrl = config('services.shopify.url');
        $this->accessToken = config('services.shopify.access_token');

        if (!$this->shopUrl || !$this->accessToken) {
            Log::error('Shopify URL or access token is not set.');
            throw new \Exception('Shopify URL or access token is not set.');
        }
    }

    //Create a new product in Shopify using GraphQL.
    public function createProduct(array $productData)
    {
        $query = <<<QUERY
            mutation createProduct(\$input: ProductInput!, \$media:[CreateMediaInput!]) {
                productCreate(input: \$input, media: \$media) {
                    product {
                        id
                        title
                        variants(first: 1) {
                            edges {
                                node {
                                    id
                                    title
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        QUERY;

        // Only include fields that are directly supported by ProductInput
        $variables = [
            'input' => [
                'title' => $productData['title'] ?? 'No Title',
                'productType' => $productData['product_type'] ?? 'Default',
                'vendor' => $productData['vendor'] ?? 'No Vendor',
                'descriptionHtml' => $productData['body_html'] ?? '',
                'productOptions' => ['name' => 'Size', 'values' => ['name' => '000']],
            ],
            'media' => [
                'mediaContentType' => 'IMAGE',
                'originalSource' => 'https://suitor.co.uk/wp-content/uploads/2020/10/28.07.23_SUITOR_15263.jpg',
            ],
        ];

        $response = $this->shopifyGraphqlRequest($query, $variables);

        if (isset($response['errors']) || !empty($response['data']['productCreate']['userErrors'])) {
            Log::error('Failed to create product in Shopify.', ['response' => $response]);
            throw new \Exception('Failed to create product in Shopify');
        }

        $product = $response['data']['productCreate']['product'];

        // Check if there are variants to create
        if (!empty($productData['variants'])) {
            $this->createProductVariants($product['id'], $productData['variants']);

            //delete dummy variant created by productCreate mutation
            $dummyVariantId = $product['variants']['edges'][0]['node']['id'];
            $this->removeDummyVariant($dummyVariantId);
        }

        return $product;
    }

    //Create variants of product
    public function createProductVariants(string $productId, array $variants)
    {
        $query = <<<QUERY
            mutation productVariantsBulkCreate(\$productId: ID!, \$variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkCreate(productId: \$productId, variants: \$variants) {
                    product {
                        id
                    }
                    productVariants {
                        id
                        metafields(first: 1) {
                            edges {
                                node {
                                    namespace
                                    key
                                    value
                                }
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        QUERY;

        $locationId = Location::pluck('shopify_location_id')->first();
        if ($locationId){
            // Prepare the variants array for the mutation
            $variantInputs = [];
            foreach ($variants as $variant) {
                $variantInputs[] = [
                    'optionValues' => [
                        [
                            'optionName' => 'Size',
                            'name' => $variant['option1'] ?? 'Default Option',
                        ],
                    ],
                    "inventoryItem" => [
                        "sku" => $variant['sku'] ?? null,
                    ],
                    "inventoryQuantities" => [
                        "availableQuantity" => $variant['inventory_quantity'] ?? null,
                        "locationId" => $locationId,
                    ],
                    'price' => $variant['price'] ?? '0.00',
                ];
            }

            $variables = ['productId' => $productId, 'variants' => $variantInputs];
            $response = $this->shopifyGraphqlRequest($query, $variables);

            if (isset($response['errors']) || !empty($response['data']['productVariantsBulkCreate']['userErrors'])) {
                Log::error('Failed to create variants in Shopify.', ['response' => $response]);
                throw new \Exception('Failed to create variants in Shopify');
            }

            Log::info('Successfully created variants in Shopify.', ['response' => $response]);
            return $response['data']['productVariantsBulkCreate']['productVariants'];
        }
        Log::error('Failed to create variants in Shopify as locationId not found.');
        throw new \Exception('Failed to create variants in Shopify as locationId not found.');
    }

    //Remove dummy variant created while creating product
    public function removeDummyVariant(string $id)
    {
        $query = <<<QUERY
            mutation productVariantDelete(\$id: ID!) {
                productVariantDelete(id: \$id) {
                    deletedProductVariantId
                    product {
                        id
                        title
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        QUERY;

        // Define variables to find and delete the dummy option and variant
        $variables = ['id' => $id];
        $response = $this->shopifyGraphqlRequest($query, $variables);

        if (isset($response['errors']) || !empty($response['data']['productVariantDelete']['userErrors'])) {
            Log::error('Failed to delete dummy option/variant in Shopify.', ['response' => $response, 'id' => $id]);
            throw new \Exception('Failed to delete dummy option/variant in Shopify');
        }

        Log::info('Successfully removed dummy option/variant in Shopify.', ['response' => $response]);
    }

    //Check whether the collection is created or not. Create it if not exist
    public function getOrCreateCollection(string $collectionTitle, string $collectionDesc)
    {
        // Query to check if the collection exists
        $query = <<<QUERY
            query getCollections(\$title: String!) {
                collections(first: 1, query: \$title) {
                    edges {
                        node {
                            id
                            title
                        }
                    }
                }
            }
        QUERY;

        $variables = ['title' => $collectionTitle];
        $response = $this->shopifyGraphqlRequest($query, $variables);

        if (isset($response['data']['collections']['edges'][0])) {
            return $response['data']['collections']['edges'][0]['node']['id'];
        } else {
            // If collection does not exist, create it
            $query = <<<QUERY
                mutation CollectionCreate(\$input: CollectionInput!) {
                    collectionCreate(input: \$input) {
                        collection {
                            id
                            title
                        }
                        userErrors {
                            field
                            message
                        }
                    }
                }
            QUERY;

            $variables = [
                'input' => ['title' => $collectionTitle, 'descriptionHtml' => $collectionDesc],
            ];

            $response = $this->shopifyGraphqlRequest($query, $variables);

            if (isset($response['errors']) || !empty($response['data']['collectionCreate']['userErrors'])) {
                Log::error('Failed to create collection in Shopify.', ['response' => $response]);
                throw new \Exception('Failed to create collection in Shopify');
            }

            return $response['data']['collectionCreate']['collection']['id'];
        }
    }

    //Adding product to collection
    public function addProductToCollection(string $id, string $productIds)
    {
        $query = <<<QUERY
            mutation collectionAddProductsV2(\$id: ID!, \$productIds: [ID!]! ) {
                collectionAddProductsV2(id: \$id, productIds: \$productIds) {
                    job {
                        done
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        QUERY;

        $variables = ['id' => $id, 'productIds' => [$productIds]];
        $response = $this->shopifyGraphqlRequest($query, $variables);

        if (isset($response['errors']) || !empty($response['data']['collectionAddProductsV2']['userErrors'])) {
            Log::error('Failed to add product to collection in Shopify.', ['response' => $response]);
            throw new \Exception('Failed to add product to collection in Shopify');
        }

        Log::info('Successfully added product to collection in Shopify.', ['response' => $response]);
    }

    //Update a product by Id in Shopify using GraphQL.
    public function updateProduct(string $shopifyProductId, array $productData)
    {
        $query = <<<QUERY
            mutation updateProduct(\$input: ProductInput!) {
                productUpdate(input: \$input) {
                    product {
                        id
                        title
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        QUERY;

        // Set the variables to be used in the mutation
        $variables = [
            'input' => [
                'id' => $shopifyProductId,
                'title' => $productData['title'] ?? 'No Title',
                'descriptionHtml' => $productData['body_html'] ?? '',
                'productType' => $productData['product_type'] ?? 'Default',
                'vendor' => $productData['vendor'] ?? 'No Vendor',
            ],
        ];

        $response = $this->shopifyGraphqlRequest($query, $variables);

        // Check for errors in the response
        if (isset($response['errors']) || !empty($response['data']['productUpdate']['userErrors'])) {
            Log::error('Failed to update product in Shopify.', [
                'response' => $response,
            ]);

            throw new \Exception('Failed to update product in Shopify');
        }

        Log::info('Successfully updated product in Shopify.', ['response' => $response]);

        return $response['data']['productUpdate']['product'];
    }

    public function publishProduct(string $productId)
    {
        // Fetch all publication IDs from the 'publications' table
        $publicationIds = Publication::where('name', 'Online Store')
                                        ->pluck('shopify_publication_id')
                                        ->toArray();

        // If there are no publication IDs, log the error and exit
        if (empty($publicationIds)) {
            Log::error('No publication IDs found in the database.');
            return;
        }

        $query = <<<QUERY
            mutation publishablePublish(\$id: ID!, \$input: [PublicationInput!]!) {
                publishablePublish(id: \$id, input: \$input) {
                    publishable {
                        availablePublicationsCount {
                            count
                        }
                        resourcePublicationsCount {
                            count
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        QUERY;

        // Prepare publication input for each publication ID
        $publicationInput = [];
        foreach ($publicationIds as $publicationId) {
            $publicationInput[] = [
                'publicationId' => $publicationId,
            ];
        }

        $variables = [
            'id' => $productId, // The Shopify Product ID (e.g., gid://shopify/Product/123456)
            'input' => $publicationInput,
        ];

        $response = $this->shopifyGraphqlRequest($query, $variables);

        if (isset($response['errors']) || !empty($response['data']['publishablePublish']['userErrors'])) {
            Log::error('Failed to publish product in Shopify.', ['response' => $response]);
            throw new \Exception('Failed to publish product in Shopify');
        }

        Log::info('Successfully published product in Shopify.', ['response' => $response]);
        return $response['data']['publishablePublish']['publishable'];
    }

    //Main function for connecting to Shopify for request and response
    protected function shopifyGraphqlRequest(string $query, array $variables = [])
    {
        try {
            $requestBody = ['query' => $query];

            // Only include variables if they are provided
            if (!empty($variables)) {
                $requestBody['variables'] = $variables;
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json', 'X-Shopify-Access-Token' => $this->accessToken,
            ])->post("https://{$this->shopUrl}/admin/api/2024-07/graphql.json", $requestBody);

            if ($response->successful()) {
                $decodedResponse = $response->json();
                // Log::info('Shopify API response', ['response' => $decodedResponse]);
                return $decodedResponse;
            } else {
                Log::error('Shopify API request failed', ['response' => $response->body()]);
                throw new \Exception('Shopify API request failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Shopify API request failed', ['message' => $e->getMessage(), 'query' => $query, 'variables' => $variables]);
            throw $e;
        }
    }

    //Get publications for publishing products to channels - one time
    public function getPublications()
    {
        $query = <<<QUERY
            query {
                publications(first: 5) {
                    edges {
                        node {
                            id
                            name
                            supportsFuturePublishing
                        }
                    }
                }
            }
        QUERY;

        $response = $this->shopifyGraphqlRequest($query);

        if (isset($response['errors'])) {
            Log::error('Failed to query publications in Shopify.', ['response' => $response]);
            throw new \Exception('Failed to query publications in Shopify');
        }

        // Extract the publications from response and store them in DB
        $publications = $response['data']['publications']['edges'];
        foreach ($publications as $publicationData) {
            $publication = $publicationData['node'];

            // Create or update publication
            Publication::updateOrCreate(
                ['shopify_publication_id' => $publication['id']],
                [
                    'name' => $publication['name'],
                    'supports_future_publishing' => $publication['supportsFuturePublishing'],
                ]
            );
        }

        Log::info('Successfully queried and stored all publications from Shopify.', ['response' => $response]);
    }

    //Get locations for adding quantity to inventory in createProductVariants - one time
    public function getLocations()
    {
        $query = <<<QUERY
            query {
                locations(first: 5) {
                    edges {
                        node {
                            id
                            name
                            address {
                                formatted
                            }
                        }
                    }
                }
            }
        QUERY;

        $response = $this->shopifyGraphqlRequest($query);

        if (isset($response['errors'])) {
            Log::error('Failed to query locations in Shopify.', ['response' => $response]);
            throw new \Exception('Failed to query locations in Shopify');
        }

        // Extract the locations from response and store them in DB
        $locations = $response['data']['locations']['edges'];
        foreach ($locations as $locationData) {
            $location = $locationData['node'];

            // Create or update location
            Location::updateOrCreate(
                ['shopify_location_id' => $location['id']],
                [
                    'name' => $location['name'],
                    'address' => implode(', ', $location['address']['formatted'] ?? [])
                ]
            );
        }

        Log::info('Successfully queried and stored all locations from Shopify.', ['response' => $response]);
    }

    //Create product options for product - crrently in no use
    public function createProductOptions(string $productId, array $variants)
    {
        // $query = <<<QUERY
        //     mutation createOptions(\$productId: ID!, \$options: [OptionCreateInput!]!) {
        //         productOptionsCreate(productId: \$productId, options: \$options) {
        //             userErrors {
        //                 field
        //                 message
        //                 code
        //             }
        //             product {
        //                 id
        //                 options {
        //                     id
        //                     name
        //                     values
        //                 }
        //             }
        //         }
        //     }
        // QUERY;

        // Extract unique size options from variants
        // $optionValues = [];
        // foreach ($variants as $variant) {
        //     if (isset($variant['option1'])) {
        //         $sizeValue = (string) $variant['option1']; // Ensure it's a string
        //         if (!in_array($sizeValue, $optionValues)) {
        //             $optionValues[] = ['name' => $sizeValue]; // Wrap value inside a 'name' key
        //         }
        //     }
        // }

        // Log to check the values being sent
        // Log::info('Unique option values:', ['optionValues' => $optionValues]);

        // Prepare the options input using the correct structure
        // $optionInputs = [
        // ['name' => 'Size', 'values' => $optionValues],
        // ['name' => 'Size', 'values' => ['name' => '000']],
        // ];
        // $variables = ['productId' => $productId, 'options' => $optionInputs];
        // // Log variables to check correctness before making API call
        // Log::info('Creating product options with variables:', $variables);
        // $response = $this->shopifyGraphqlRequest($query, $variables);

        // if (isset($response['errors']) || !empty($response['data']['productOptionsCreate']['userErrors'])) {
        //     Log::error('Failed to create options in Shopify.', [
        //         'response' => $response,
        //     ]);

        //     throw new \Exception('Failed to create options in Shopify');
        // }
        // // return $response;
        // // After options are created, create variants
        // $this->createProductVariants($productId, $variants);
    }
}
