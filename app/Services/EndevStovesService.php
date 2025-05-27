<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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

    public function getApiToken()
    {
        $cachedToken = Cache::get('endevstoves_api_token');
        if ($cachedToken) {
            return $cachedToken;
        }
        $response = Http::post($this->apiUrl . '/users/login/', [
            'username' => $this->username,
            'password' => $this->password,
        ]);
        if ($response->successful()) {
            $token = $response->json()['token'];
            Cache::put('endevstoves_api_token', $token, now()->addHours(23));
            return $token;
        }
        throw new \Exception('Could not authenticate with EndevStoves API: ' . $response->body());
    }

    public function fetchProducts()
    {
        $response = Http::withToken($this->getApiToken())
            ->get($this->apiUrl . '/products/');
        if ($response->successful()) {
            $products = $response->json();
            return $products;
        }
        throw new \Exception("Failed to fetch products: " . $response->body());
    }

    public function fetchCustomers()
    {
        $response = Http::withToken($this->getApiToken())
            ->get($this->apiUrl . '/customers/');
        if ($response->successful()) {
            $customers = $response->json();
            return $customers;
        }
        throw new \Exception("Failed to fetch customers: " . $response->body());
    }

    public function fetchStaff()
    {
        $response = Http::withToken($this->getApiToken())
            ->get($this->apiUrl . '/staff/');
        if ($response->successful()) {
            $staff = $response->json();
            return $staff;
        }
    }

    public function createCustomer($customerData)
    {
        $response = Http::withToken($this->getApiToken())
            ->post($this->apiUrl . '/customers/create/', $customerData);
        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception("Failed to create customer: " . $response->body());
    }

    public function createSale($saleData)
    {
        $response = Http::withToken($this->getApiToken())
            ->post($this->apiUrl . '/sales/create/', $saleData);
        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception("Failed to create sale: " . $response->body());
    }

    public function checkStockAvailability($productId, $isGreen = false)
    {
        $response = Http::withToken($this->getApiToken())
                ->post($this->apiUrl . '/stock/');
        if ($response->successful()) {
            $allStock = $response->json();
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
            $filteredStocks = array_values($filteredStocks);
            if (empty($filteredStocks)) {
                return [
                    'available' => false,
                    'quantity' => 0,
                    'product_id' => $productId,
                    'is_green' => $isGreen
                ];
            }
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
    }
}
