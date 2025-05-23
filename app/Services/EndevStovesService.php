<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EndevStovesService
{
    protected $apiUrl;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->apiUrl = config('services.endevstoves.url');
        $this->username = config('services.endevstoves.username');
        $this->password = config('services.endevstoves.password');
    }

    /**
     * Get API authentication token
     *
     * @return string
     */
    public function getApiToken()
    {
        // Check if token is cached
        $cachedToken = Cache::get('endevstoves_api_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        // Get authentication token from EndevStoves
        $response = Http::post($this->apiUrl . '/users/login/', [
            'username' => $this->username,
            'password' => $this->password,
        ]);
        
        if ($response->successful()) {
            $token = $response->json()['token'];
            // Cache token for 23 hours (assuming token expiry is 24 hours)
            Cache::put('endevstoves_api_token', $token, now()->addHours(23));
            return $token;
        }
        
        throw new \Exception('Could not authenticate with EndevStoves API: ' . $response->body());
    }

    /**
     * Fetch products from API
     *
     * @return array
     */
    public function fetchProducts()
    {
        try {
            $response = Http::withToken($this->getApiToken())
                ->get($this->apiUrl . '/products/');
            
            if ($response->successful()) {
                // Cache products for 1 hour
                $products = $response->json();
                Cache::put('endevstoves_products', $products, now()->addHour());
                return $products;
            }
            
            throw new \Exception("Failed to fetch products: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Error fetching products: " . $e->getMessage());
            // Return cached products if available
            $cachedProducts = Cache::get('endevstoves_products', []);
            if (!empty($cachedProducts)) {
                Log::info("Returning cached products due to API error");
                return $cachedProducts;
            }
            throw $e;
        }
    }

    /**
     * Fetch customers from API
     *
     * @return array
     */
    public function fetchCustomers()
    {
        try {
            $response = Http::withToken($this->getApiToken())
                ->get($this->apiUrl . '/customers/');
            
            if ($response->successful()) {
                $customers = $response->json();
                return $customers;
            }
            
            throw new \Exception("Failed to fetch customers: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Error fetching customers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch staff from API
     *
     * @return array
     */
    public function fetchStaff()
    {
        try {
            $response = Http::withToken($this->getApiToken())
                ->get($this->apiUrl . '/staff/');
            
            if ($response->successful()) {
                $staff = $response->json();
                return $staff;
            }
            
            throw new \Exception("Failed to fetch staff: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Error fetching staff: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new customer
     *
     * @param array $customerData
     * @return array
     */
    public function createCustomer($customerData)
    {
        try {
            $response = Http::withToken($this->getApiToken())
                ->post($this->apiUrl . '/customers/create/', $customerData);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            throw new \Exception("Failed to create customer: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Error creating customer: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a sale
     *
     * @param array $saleData
     * @return array
     */
    public function createSale($saleData)
    {
        $saleData['member'] = "";
        try {
            $response = Http::withToken($this->getApiToken())
                ->post($this->apiUrl . '/sales/create/', $saleData);
            
            if ($response->successful()) {
                return $response->json();
            }
            throw new \Exception("Failed to create sale: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Error creating sale: " . $e->getMessage(), ['sale_data' => $saleData]);
            throw $e;
        }
    }

    /**
     * Check stock availability
     *
     * @param int $productId
     * @param bool $isGreen
     * @return array
     */
    public function checkStockAvailability($productId, $isGreen = false)
    {
        try {
            // Get stock information from the correct endpoint
            $response = Http::withToken($this->getApiToken())
                ->post($this->apiUrl . '/stock/');
            
            if ($response->successful()) {
                $allStock = $response->json();
                
                Log::info("Retrieved stock data", [
                    'count' => count($allStock),
                    'checking_product' => $productId
                ]);
                
                // Filter stock items for the requested product
                $filteredStocks = array_filter($allStock, function($item) use ($productId) {
                    return isset($item['product']) && $item['product'] == $productId;
                });
                
                // Further filter by green/non-green status (Moulding method = green)
                $filteredStocks = array_filter($filteredStocks, function($item) use ($isGreen) {
                    if ($isGreen) {
                        return isset($item['method']) && $item['method'] === 'Moulding';
                    } else {
                        // Non-green products use methods other than Moulding (Firing, Cladding, etc.)
                        return isset($item['method']) && $item['method'] !== 'Moulding';
                    }
                });
                
                // Reset array keys
                $filteredStocks = array_values($filteredStocks);
                
                Log::info("Filtered stock for product", [
                    'product_id' => $productId,
                    'is_green' => $isGreen,
                    'matching_items' => count($filteredStocks)
                ]);
                
                if (empty($filteredStocks)) {
                    // No stock found for this product with the specified criteria
                    return [
                        'available' => false,
                        'quantity' => 0,
                        'product_id' => $productId,
                        'is_green' => $isGreen
                    ];
                }
                
                // Calculate total quantity available (from countInStock field)
                $totalQuantity = 0;
                foreach ($filteredStocks as $stock) {
                    $totalQuantity += isset($stock['countInStock']) ? (int)$stock['countInStock'] : 0;
                }
                
                return [
                    'available' => $totalQuantity > 0,
                    'quantity' => $totalQuantity,
                    'product_id' => $productId,
                    'is_green' => $isGreen,
                    'stocks' => $filteredStocks
                ];
            }
            
            throw new \Exception("Failed to check stock availability: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Error checking stock: " . $e->getMessage(), [
                'product_id' => $productId,
                'is_green' => $isGreen
            ]);
            
            // In case of error, we need to be honest about not knowing the stock
            return [
                'available' => false,
                'quantity' => 0,
                'product_id' => $productId,
                'is_green' => $isGreen,
                'error' => 'Unable to check stock at this time',
                'is_fallback' => true // Flag to indicate this is a fallback response
            ];
        }
    }
}
