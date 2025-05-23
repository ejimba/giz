<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Support\Facades\Log;

class SalesConversationService
{
    protected $twilioService;
    protected $endevStovesService;
    
    // Navigation history to track previous steps
    protected $navigationSteps = [
        'staff_selection' => 'initial',
        'customer_selection' => 'staff_selection',
        'date_selection' => 'customer_selection',
        'product_selection' => 'date_selection',
        'quantity' => 'product_selection',
        'unit_price' => 'quantity',
        'green_product' => 'unit_price',
        'add_more_products' => 'green_product',
        'credit_sale' => 'add_more_products',
        'deposit' => 'credit_sale',
        'confirmation' => 'deposit',
        'stock_product_selection' => 'initial',
        'stock_green_product' => 'stock_product_selection',
        'stock_check_result' => 'stock_green_product'
    ];
    
    // Property to store the cart items
    protected $cartItems = [];

    public function __construct(TwilioService $twilioService, EndevStovesService $endevStovesService)
    {
        $this->twilioService = $twilioService;
        $this->endevStovesService = $endevStovesService;
    }

    /**
     * Start a new sales conversation flow
     *
     * @param Client $client
     * @param string|null $initialMessage
     * @return Conversation
     */
    public function startSalesConversation(Client $client, $initialMessage = null): Conversation
    {
        // Find the first sales prompt
        $startingPrompt = Prompt::where('active', true)
            ->where('title', 'Sales Menu')
            ->whereJsonContains('metadata->is_sales_flow', true)
            ->first();

        if (!$startingPrompt) {
            throw new \Exception('No sales flow prompts found');
        }

        // Create the conversation
        $conversation = Conversation::create([
            'client_id' => $client->id,
            'title' => 'Sales Report ' . now()->format('Y-m-d H:i'),
            'current_prompt_id' => $startingPrompt->id,
            'status' => 'active',
            'started_at' => now(),
            'metadata' => [
                'starting_prompt_id' => $startingPrompt->id,
                'is_sales_flow' => true,
                'step' => 'initial',
            ],
        ]);

        Log::info('New sales conversation started', [
            'conversation_id' => $conversation->id,
            'client_id' => $client->id,
        ]);

        // If we already know the user's initial selection, process it immediately
        // without sending the welcome message first (to avoid duplicate menus)
        if ($initialMessage === '2') {
            // Direct to stock check flow if user sent '2'
            $this->processResponse($conversation, '2');
        } else if ($initialMessage === '1') {
            // Direct to sales flow if user sent '1'
            $this->processResponse($conversation, '1');
        } else {
            // Only send the welcome menu if there's no initial message
            $this->twilioService->sendWhatsAppMessage(
                $client->phone,
                $startingPrompt->content
            );
        }

        return $conversation;
    }

    /**
     * Process a response from the client
     *
     * @param Conversation $conversation
     * @param string $messageBody
     */
    public function processResponse(Conversation $conversation, string $messageBody)
    {
        $currentPromptId = $conversation->current_prompt_id;
        $currentPrompt = Prompt::find($currentPromptId);
        $metadata = $conversation->metadata ?: [];
        $step = $metadata['step'] ?? 'initial';
        
        Log::info('Processing response', [
            'conversation_id' => $conversation->id,
            'current_prompt' => $currentPrompt ? $currentPrompt->title : 'None',
            'message' => $messageBody,
            'step' => $step
        ]);
        
        // Handle navigation options (except in main menu)
        if ($step !== 'initial' && $currentPrompt && $currentPrompt->title !== 'Sales Menu') {
            if ($messageBody === '00') {
                // Go to main menu
                Log::info('User requested to return to main menu', ['conversation_id' => $conversation->id]);
                $this->resetToMainMenu($conversation);
                return;
            } else if ($messageBody === '0') {
                // Go back to previous step
                Log::info('User requested to go back', ['conversation_id' => $conversation->id]);
                $this->goBackToPreviousStep($conversation);
                return;
            }
        }
        
        if ($step === 'initial') {
            $this->processInitialResponse($conversation, $messageBody);
            return;
        }

        $client = $conversation->client;
        $currentPrompt = $conversation->currentPrompt;
        $metadata = $conversation->metadata ?? [];
        $step = $metadata['step'] ?? 'initial';

        // Create a response record
        $response = Response::create([
            'client_id' => $client->id,
            'prompt_id' => $currentPrompt->id,
            'conversation_id' => $conversation->id,
            'content' => $messageBody,
            'received_at' => now(),
            'metadata' => [
                'prompt_type' => $currentPrompt->type,
                'prompt_title' => $currentPrompt->title,
                'step' => $step
            ]
        ]);

        // Process the response based on current step
        try {
            switch ($step) {
                case 'initial':
                    return $this->processInitialResponse($conversation, $messageBody);
                case 'product_selection':
                    return $this->processProductSelection($conversation, $messageBody);
                case 'customer_selection':
                    return $this->processCustomerSelection($conversation, $messageBody);
                case 'new_customer_name':
                    return $this->processNewCustomerName($conversation, $messageBody);
                case 'new_customer_phone':
                    return $this->processNewCustomerPhone($conversation, $messageBody);
                case 'handle_customer_creation_error':
                    return $this->handleCustomerCreationError($conversation, $messageBody);
                case 'staff_selection':
                    return $this->processStaffSelection($conversation, $messageBody);
                case 'date_selection':
                    return $this->processDateSelection($conversation, $messageBody);
                case 'quantity':
                    return $this->processQuantity($conversation, $messageBody);
                case 'unit_price':
                    return $this->processUnitPrice($conversation, $messageBody);
                case 'green_product':
                    return $this->processGreenProduct($conversation, $messageBody);
                case 'add_more_products':
                    return $this->processAddMoreProducts($conversation, $messageBody);
                case 'credit_sale':
                    return $this->processCreditSale($conversation, $messageBody);
                case 'deposit':
                    return $this->processDeposit($conversation, $messageBody);
                case 'confirmation':
                    return $this->processConfirmation($conversation, $messageBody);
                // Stock check flow
                case 'stock_product_selection':
                    return $this->processStockProductSelection($conversation, $messageBody);
                case 'stock_green_product':
                    return $this->processStockGreenProduct($conversation, $messageBody);
                case 'stock_check_result':
                    return $this->processStockCheckResult($conversation, $messageBody);
                default:
                    // Unknown step
                    $this->twilioService->sendWhatsAppMessage(
                        $client->phone,
                        "Sorry, something went wrong. Please send '1' to start again."
                    );
                    $conversation->status = 'error';
                    $conversation->save();
                    return;
            }
        } catch (\Exception $e) {
            Log::error('Error processing sales conversation response', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
                'step' => $step,
            ]);

            $this->twilioService->sendWhatsAppMessage(
                $client->phone,
                "Sorry, an error occurred: " . $e->getMessage() . "\n\nPlease send '1' to start again."
            );

            $conversation->status = 'error';
            $conversation->save();
        }
    }

    /**
     * Process initial menu response
     */
    private function processInitialResponse($conversation, $message)
    {
        $option = trim($message);
        
        if ($option === "1") {
            // User selected "Record a Sale" - start with staff selection
            return $this->fetchAndDisplayStaff($conversation);
        } else if ($option === "2") {
            // User selected "Check Stock Availability"
            return $this->fetchProductsForStockCheck($conversation);
        } else {
            // Invalid option
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid option. Please reply with:\n1. Record a Sale\n2. Check Stock Availability"
            );
            return false;
        }
    }
    
    /**
     * Fetch products for stock availability check
     */
    private function fetchProductsForStockCheck($conversation)
    {
        try {
            // Fetch products from API
            $products = $this->endevStovesService->fetchProducts();
            
            if (empty($products)) {
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "No products available. Please try again later."
                );
                return false;
            }
            
            // Format product list
            $productList = "Select a product to check stock:\n";
            foreach ($products as $index => $product) {
                $productList .= ($index + 1) . ". " . $product['name'] . " " . $product['type'] . "\n";
            }
            
            // Store products in conversation metadata
            $metadata = $conversation->metadata;
            $metadata['step'] = 'stock_product_selection';
            $metadata['products'] = $products;
            $metadata['is_stock_check'] = true; // Flag to indicate this is a stock check flow
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Advance to stock product selection prompt
            $nextPrompt = Prompt::where('active', true)
                ->where('title', 'Stock Check Product Selection')
                ->first();
                
            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }
            
            // Send product list
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone, 
                $productList
            );
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error fetching products for stock check: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Sorry, we couldn't retrieve the product list. Please try again."
            );
            return false;
        }
    }

    /**
     * Fetch products and display them for selection
     */
    private function fetchAndDisplayProducts($conversation)
    {
        try {
            // Fetch products from API
            $products = $this->endevStovesService->fetchProducts();

            if (empty($products)) {
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "No products available. Please try again later."
                );
                return false;
            }

            // Format product list
            $productList = "Select a product:\n";
            foreach ($products as $index => $product) {
                $productList .= ($index + 1) . ". " . $product['name'] . " " . $product['type'] . "\n";
            }

            // Store products in conversation metadata
            $metadata = $conversation->metadata;
            $metadata['step'] = 'product_selection';
            $metadata['products'] = $products;
            $conversation->metadata = $metadata;
            $conversation->save();

            // Advance to product selection prompt
            $nextPrompt = Prompt::where('active', true)
                ->where('title', 'Product Selection')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            // Send product list
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                $productList
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Error fetching products: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Sorry, we couldn't retrieve the product list. Please try again."
            );
            return false;
        }
    }

    /**
     * Process product selection
     */
    private function processProductSelection($conversation, $message)
    {
        $metadata = $conversation->metadata;
        $products = $metadata['products'];

        $selection = (int)trim($message) - 1;

        if ($selection < 0 || $selection >= count($products)) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please choose a number from the list."
            );
            return false;
        }

        // Store selected product temporarily
        $metadata['selected_product'] = $products[$selection];
        $conversation->metadata = $metadata;
        $conversation->save();

        // Ask for green product status first before checking stock
        $metadata['step'] = 'green_product';
        $conversation->metadata = $metadata;
        $conversation->save();
        
        // Update prompt
        $nextPrompt = Prompt::where('active', true)
            ->where('title', 'Green Product')
            ->first();

        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }

        // Ask about green product
        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            "Is this a green product sale?\n1. Yes\n2. No"
        );

        return true;
    }
    /**
     * Process customer selection
     */
    private function processCustomerSelection($conversation, $message)
    {
        $metadata = $conversation->metadata;
        $customers = $metadata['customers'];

        $selection = (int)trim($message) - 1;

        if ($selection === count($customers)) {
            // User selected "Create New Customer"
            return $this->startNewCustomerFlow($conversation);
        }

        if ($selection < 0 || $selection >= count($customers)) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please choose a number from the list."
            );
            return false;
        }

        // Store selected customer
        $metadata['selected_customer'] = $customers[$selection];
        $metadata['step'] = 'date_selection';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Update prompt
        $nextPrompt = \App\Models\Prompt::where('active', true)
            ->where('title', 'Sale Date')
            ->first();

        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }

        // Prompt for date
        $today = date('d/m/Y');
        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            "Enter sale date (DD/MM/YYYY) or press 1 to use today's date ($today):"
        );

        return true;
    }

    private function startNewCustomerFlow($conversation)
    {
        $metadata = $conversation->metadata;
        $metadata['creating_customer'] = true;
        $metadata['step'] = 'new_customer_name';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Update prompt
        $nextPrompt = \App\Models\Prompt::where('active', true)
            ->where('title', 'New Customer Name')
            ->first();

        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }

        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            "Creating a new customer. Please enter customer name:"
        );

        return true;
    }

    /**
     * Process new customer name
     */
    private function processNewCustomerName($conversation, $message)
    {
        $name = trim($message);

        if (empty($name)) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Name cannot be empty. Please enter customer name:"
            );
            return false;
        }

        // Store customer name
        $metadata = $conversation->metadata;
        $metadata['new_customer_name'] = $name;
        $metadata['step'] = 'new_customer_phone';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Update prompt
        $nextPrompt = \App\Models\Prompt::where('active', true)
            ->where('title', 'New Customer Phone')
            ->first();

        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }

        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            "Enter customer phone number:"
        );

        return true;
    }

    /**
     * Process new customer phone and create customer
     */
    private function processNewCustomerPhone($conversation, $message)
    {
        $phone = trim($message);

        if (empty($phone)) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Phone number cannot be empty. Please enter customer phone number:"
            );
            return false;
        }

        // Store customer phone
        $metadata = $conversation->metadata;
        $metadata['new_customer_phone'] = $phone;
        $conversation->metadata = $metadata;
        $conversation->save();

        // Create the customer
        try {
            $customerData = [
                'name' => $conversation->metadata['new_customer_name'],
                'phoneNumber' => $phone,
                'location' => '',
                'type' => '',
                'IDNumber' => '',
                'contactPerson' => '',
                'member' => ''
            ];

            $customer = $this->endevStovesService->createCustomer($customerData);

            // Store new customer and proceed to date selection
            $metadata = $conversation->metadata;
            $metadata['selected_customer'] = $customer;
            $metadata['step'] = 'date_selection';
            $metadata['creating_customer'] = false;
            $conversation->metadata = $metadata;
            $conversation->save();

            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Customer created successfully. Proceeding with sale."
            );

            // Update prompt for date selection
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Sale Date')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            // Prompt for date
            $today = date('d/m/Y');
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Enter sale date (DD/MM/YYYY) or press 1 to use today's date ($today):\n\n0 - Go back\n00 - Main menu"
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Customer creation failed: " . $e->getMessage());

            // Offer to try again or go back to customer list
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Error creating customer. Please select an option:\n" .
                "1. Try again with a different name\n" .
                "2. Go back to customer selection"
            );

            $metadata['step'] = 'handle_customer_creation_error';
            $conversation->metadata = $metadata;
            $conversation->save();

            return true;
        }
    }

    /**
     * Handle customer creation error
     */
    private function handleCustomerCreationError($conversation, $message)
    {
        $option = trim($message);

        if ($option === "1") {
            // Try again - go back to name input
            return $this->startNewCustomerFlow($conversation);
        } else if ($option === "2") {
            // Go back to customer selection
            $metadata = $conversation->metadata;
            $metadata['creating_customer'] = false;
            $metadata['step'] = 'customer_selection';
            $conversation->metadata = $metadata;
            $conversation->save();

            // Fetch customers again
            try {
                $customers = $this->endevStovesService->fetchCustomers();
                
                // Format customer list
                $customerList = "Select a customer:\n";
                foreach ($customers as $index => $customer) {
                    $customerList .= ($index + 1) . ". " . $customer['name'] . "\n";
                }
                $customerList .= (count($customers) + 1) . ". Create New Customer";
                
                // Store customers in metadata
                $metadata['customers'] = $customers;
                $conversation->metadata = $metadata;
                $conversation->save();
                
                // Update prompt
                $nextPrompt = Prompt::where('active', true)
                    ->where('title', 'Customer Selection')
                    ->first();
                    
                if ($nextPrompt) {
                    $conversation->current_prompt_id = $nextPrompt->id;
                    $conversation->save();
                }
                
                // Send customer list
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    $customerList
                );
                
                return true;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Error fetching customers: " . $e->getMessage());
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "Sorry, we couldn't retrieve the customer list. Please try again later."
                );
                return false;
            }
        } else {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid option. Please select 1 to try again or 2 to go back to customer selection."
            );
            return false;
        }
    }

    /**
     * Fetch and display staff list
     */
    private function fetchAndDisplayStaff($conversation)
    {
        try {
            $staff = $this->endevStovesService->fetchStaff();

            // Format staff list
            $staffList = "Select a staff member:\n";
            foreach ($staff as $index => $member) {
                $staffList .= ($index + 1) . ". " . $member['name'] . "\n";
            }
            $staffList .= "\n00 - Main menu";

            // Store staff in metadata and explicitly set the step
            $metadata = $conversation->metadata;
            $metadata['staff'] = $staff;
            $metadata['step'] = 'staff_selection';
            $conversation->metadata = $metadata;
            $conversation->save();

            // Update prompt
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Staff Selection')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            // Send staff list
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                $staffList
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error fetching staff: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Sorry, we couldn't retrieve the staff list. Please try again."
            );
            return false;
        }
    }

    private function processStaffSelection($conversation, $message)
    {
        $metadata = $conversation->metadata;
        $staff = $metadata['staff'];

        $selection = (int)trim($message) - 1;

        if ($selection < 0 || $selection >= count($staff)) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please choose a number from the list."
            );
            return false;
        }

        // Store selected staff
        $metadata['selected_staff'] = $staff[$selection];
        $metadata['step'] = 'customer_selection';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Fetch customers
        try {
            $customers = $this->endevStovesService->fetchCustomers();

            // Format customer list
            $customerList = "Select a customer:\n";
            foreach ($customers as $index => $customer) {
                $customerList .= ($index + 1) . ". " . $customer['name'] . "\n";
            }
            $customerList .= (count($customers) + 1) . ". Create New Customer";

            // Store customers in metadata
            $metadata['customers'] = $customers;
            $conversation->metadata = $metadata;
            $conversation->save();

            // Update prompt
            $nextPrompt = Prompt::where('active', true)
                ->where('title', 'Customer Selection')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            // Send customer list
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                $customerList
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Error fetching customers: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Sorry, we couldn't retrieve the customer list. Please try again."
            );
            return false;
        }
    }

    /**
     * Process date selection
     */
    private function processDateSelection($conversation, $message)
    {
        $message = trim($message);
        $date = null;

        if ($message === "1") {
            // Use today's date
            $date = date('Y-m-d');
        } else {
            // Parse user-provided date
            try {
                $dateObj = \DateTime::createFromFormat('d/m/Y', $message);
                if ($dateObj) {
                    $date = $dateObj->format('Y-m-d');
                }
            } catch (\Exception $e) {
                // Date parsing failed
            }
        }

        if (!$date) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid date format. Please use DD/MM/YYYY or press 1 for today's date."
            );
            return false;
        }

        // Store date
        $metadata = $conversation->metadata;
        $metadata['sale_date'] = $date;
        // Initialize empty cart for products
        $metadata['cart'] = [];
        $metadata['step'] = 'product_selection';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Proceed to product selection
        return $this->fetchAndDisplayProducts($conversation);
    }

    /**
     * Process quantity
     */
    private function processQuantity($conversation, $message)
    {
        $quantity = (int)trim($message);

        if ($quantity <= 0) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Quantity must be a positive number. Please try again."
            );
            return false;
        }

        // Validate against available stock
        try {
            $metadata = $conversation->metadata;
            $availableStock = $metadata['available_stock'] ?? 0;
            
            if ($quantity > $availableStock) {
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "Sorry, only {$availableStock} units are available. Please enter a smaller quantity."
                );
                return false;
            }
            
            // Store the quantity
            $metadata['quantity'] = $quantity;
            $metadata['step'] = 'unit_price';
            $conversation->metadata = $metadata;
            $conversation->save();

            // Update prompt
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Unit Price')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            // Get default price from product
            $defaultPrice = $metadata['selected_product']['price'] ?? '';

            // Prompt for unit price
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Enter unit price" . ($defaultPrice ? " (default: $defaultPrice)" : "") . ":"
            );

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error processing quantity: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Error processing quantity: " . $e->getMessage() . "\nPlease try again."
            );
            return false;
        }
    }

    /**
     * Process unit price
     */
    private function processUnitPrice($conversation, $message)
    {
        $unitPrice = (float)trim($message);

        if ($unitPrice <= 0) {
            // If user provided empty or invalid price, use default from product
            $metadata = $conversation->metadata;
            $unitPrice = $metadata['selected_product']['price'] ?? null;

            if (!$unitPrice) {
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "Unit price must be a positive number. Please try again."
                );
                return false;
            }
        }

        // Store unit price and calculate total for this item
        $metadata = $conversation->metadata;
        $metadata['unit_price'] = $unitPrice;
        $itemTotal = $unitPrice * $metadata['quantity'];
        
        // Add the current product to the cart
        $cart = $metadata['cart'] ?? [];
        $cart[] = [
            'product' => $metadata['selected_product'],
            'quantity' => $metadata['quantity'],
            'unit_price' => $unitPrice,
            'is_green' => $metadata['green'] ?? false,
            'total' => $itemTotal
        ];
        
        $metadata['cart'] = $cart;
        $metadata['step'] = 'add_more_products';
        $conversation->metadata = $metadata;
        $conversation->save();
        
        // Calculate order total across all items
        $orderTotal = 0;
        foreach ($cart as $item) {
            $orderTotal += $item['total'];
        }
        $metadata['order_total'] = $orderTotal;
        $conversation->metadata = $metadata;
        $conversation->save();

        // Update prompt for adding more products
        $nextPrompt = \App\Models\Prompt::where('active', true)
            ->where('title', 'Add More Products')
            ->first();

        if (!$nextPrompt) {
            // If the prompt doesn't exist, use the Credit Sale prompt as fallback
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Credit Sale')
                ->first();
        }

        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }

        // Format cart summary
        $cartSummary = "Current cart:\n";
        foreach ($cart as $index => $item) {
            $productName = $item['product']['name'] . ' ' . $item['product']['type'];
            $isGreen = $item['is_green'] ? ' (Green)' : '';
            $cartSummary .= ($index + 1) . ". {$productName}{$isGreen} - {$item['quantity']} units x {$item['unit_price']} = {$item['total']}\n";
        }
        $cartSummary .= "\nTotal: {$orderTotal}\n\n";
        
        // Ask if the user wants to add more products
        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            $cartSummary . "Would you like to add another product?\n1. Yes\n2. No (proceed to checkout)"
        );

        return true;
    }

    /**
     * Process green product selection
     */
    private function processGreenProduct($conversation, $message)
    {
        $selection = trim($message);

        if ($selection !== "1" && $selection !== "2") {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please reply with 1 for Yes or 2 for No."
            );
            return false;
        }

        $isGreen = ($selection === "1");

        // Check stock availability now that we know if it's a green product
        try {
            $metadata = $conversation->metadata;
            $productId = $metadata['selected_product']['_id'];
            
            // Store green product selection
            $metadata['green'] = $isGreen;
            $conversation->metadata = $metadata;
            $conversation->save();
            
            $stockInfo = $this->endevStovesService->checkStockAvailability($productId, $isGreen);

            if (!$stockInfo['available']) {
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "Sorry, there is no stock available for this product. Please try another product."
                );

                // Go back to product selection while preserving other sale information
                return $this->fetchAndDisplayProducts($conversation);
            }
            
            // Store available stock quantity for this product
            $metadata['available_stock'] = $stockInfo['quantity'];
            $metadata['step'] = 'quantity';
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Update prompt
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Quantity')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }
            
            // Ask for quantity now that we know what's available
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Available stock: {$stockInfo['quantity']} units\nEnter quantity:"
            );
            
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error checking stock: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Error checking stock: " . $e->getMessage() . "\nPlease try again."
            );
            return false;
        }
    }

    /**
     * Process add more products selection
     */
    private function processAddMoreProducts($conversation, $message)
    {
        $selection = trim($message);
        
        if ($selection !== "1" && $selection !== "2") {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please reply with 1 to add another product or 2 to proceed to checkout."
            );
            return false;
        }
        
        if ($selection === "1") {
            // User wants to add more products - go back to product selection
            $metadata = $conversation->metadata;
            $metadata['step'] = 'product_selection';
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Clear temporary product selection data but preserve the cart
            unset($metadata['selected_product']);
            unset($metadata['quantity']);
            unset($metadata['unit_price']);
            unset($metadata['green']);
            unset($metadata['available_stock']);
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Return to product selection
            return $this->fetchAndDisplayProducts($conversation);
        } else {
            // User wants to proceed to checkout
            $metadata = $conversation->metadata;
            $metadata['step'] = 'credit_sale';
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Update prompt
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Credit Sale')
                ->first();
                
            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }
            
            // Ask about credit sale
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Is this a credit sale?\n1. Yes\n2. No"
            );
            
            return true;
        }
    }
    
    /**
     * Process credit sale selection
     */
    private function processCreditSale($conversation, $message)
    {
        $selection = trim($message);

        if ($selection !== "1" && $selection !== "2") {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please reply with 1 for Yes or 2 for No."
            );
            return false;
        }

        $isCreditSale = ($selection === "1");

        // Store credit sale selection
        $metadata = $conversation->metadata;
        $metadata['on_credit'] = $isCreditSale;
        $conversation->metadata = $metadata;
        $conversation->save();

        if ($isCreditSale) {
            // If it's a credit sale, ask for deposit
            $metadata['step'] = 'deposit';
            $conversation->metadata = $metadata;
            $conversation->save();

            // Update prompt
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Deposit')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Enter deposit amount:"
            );
        } else {
            // Skip deposit for non-credit sales
            $metadata['deposit'] = 0;
            $metadata['step'] = 'confirmation';
            $conversation->metadata = $metadata;
            $conversation->save();

            // Update prompt
            $nextPrompt = \App\Models\Prompt::where('active', true)
                ->where('title', 'Confirmation')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }

            return $this->sendSaleConfirmation($conversation);
        }

        return true;
    }

    /**
     * Process deposit amount
     */
    private function processDeposit($conversation, $message)
    {
        $deposit = (float)trim($message);

        if ($deposit < 0) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Deposit cannot be negative. Please try again."
            );
            return false;
        }

        // Check if deposit is greater than total price
        $metadata = $conversation->metadata;
        $totalPrice = $metadata['total_price'];

        if ($deposit > $totalPrice) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Deposit cannot be greater than the total price ($totalPrice). Please enter a smaller amount."
            );
            return false;
        }

        // Store deposit
        $metadata['deposit'] = $deposit;
        $metadata['step'] = 'confirmation';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Update prompt
        $nextPrompt = \App\Models\Prompt::where('active', true)
            ->where('title', 'Confirmation')
            ->first();

        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }

        return $this->sendSaleConfirmation($conversation);
    }

    /**
     * Send sale confirmation
     */
    private function sendSaleConfirmation($conversation)
    {
        $metadata = $conversation->metadata;

        // Format confirmation message
        $confirmation = "Please confirm the sale details:\n\n";
        $confirmation .= "Product: " . $metadata['selected_product']['name'] . " " . $metadata['selected_product']['type'] . "\n";
        $confirmation .= "Customer: " . $metadata['selected_customer']['name'] . "\n";
        $confirmation .= "Staff: " . $metadata['selected_staff']['name'] . "\n";
        $confirmation .= "Date: " . date('d/m/Y', strtotime($metadata['sale_date'])) . "\n";
        $confirmation .= "Quantity: " . $metadata['quantity'] . "\n";
        $confirmation .= "Unit Price: " . $metadata['unit_price'] . "\n";
        $confirmation .= "Total Price: " . $metadata['total_price'] . "\n";
        $confirmation .= "Green Product: " . ($metadata['green'] ? "Yes" : "No") . "\n";
        $confirmation .= "Credit Sale: " . ($metadata['on_credit'] ? "Yes" : "No") . "\n";

        if ($metadata['on_credit']) {
            $confirmation .= "Deposit: " . $metadata['deposit'] . "\n";
        }

        $confirmation .= "\nReply:\n1. Confirm and submit\n2. Cancel";

        $this->twilioService->sendWhatsAppMessage($conversation->client->phone, $confirmation);

        return true;
    }

    /**
     * Process confirmation
     */
    private function processConfirmation($conversation, $message)
    {
        $selection = trim($message);

        if ($selection === "2") {
            // User canceled
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Sale canceled. Send '1' to start a new sale."
            );

            $conversation->status = 'canceled';
            $conversation->save();

            return true;
        }

        if ($selection !== "1") {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please reply with 1 to confirm or 2 to cancel."
            );
            return false;
        }

        // User confirmed - submit the sale
        return $this->submitSaleToAPI($conversation);
    }

    /**
     * Submit sale to API
     */
    private function submitSaleToAPI($conversation)
    {
        try {
            $metadata = $conversation->metadata;
            $cart = $metadata['cart'] ?? [];
            
            if (empty($cart)) {
                throw new \Exception('Cart is empty. No products to submit.');
            }
            
            $results = [];
            $allSuccess = true;
            
            // Process each cart item as a separate sale
            foreach ($cart as $index => $item) {
                $saleData = [
                    'product' => $item['product']['_id'],
                    'customer' => $metadata['selected_customer']['_id'],
                    'staffID' => $metadata['selected_staff']['_id'],
                    'date' => $metadata['sale_date'],
                    'quantity' => $item['quantity'],
                    'unitPrice' => $item['unit_price'],
                    'totalPrice' => $item['total'],
                    'green' => $item['is_green'] ? true : false,
                    'onCredit' => $metadata['is_credit'] ? true : false,
                ];
                
                // Add deposit if it's a credit sale (divide deposit proportionally among items)
                if ($metadata['is_credit'] && isset($metadata['deposit'])) {
                    // Calculate this item's share of the deposit based on its percentage of the total order
                    $orderTotal = $metadata['order_total'] ?? 1; // Prevent division by zero
                    $itemPercentage = $item['total'] / $orderTotal;
                    $itemDeposit = round($metadata['deposit'] * $itemPercentage, 2);
                    $saleData['deposit'] = $itemDeposit;
                } else if ($metadata['is_credit']) {
                    $saleData['deposit'] = 0;
                }
                
                // Submit this item to the API
                try {
                    $result = $this->endevStovesService->createSale($saleData);
                    $results[] = [
                        'product' => $item['product']['name'] . ' ' . $item['product']['type'],
                        'quantity' => $item['quantity'],
                        'success' => true,
                        'result' => $result
                    ];
                } catch (\Exception $e) {
                    $allSuccess = false;
                    $results[] = [
                        'product' => $item['product']['name'] . ' ' . $item['product']['type'],
                        'quantity' => $item['quantity'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    Log::error("Error submitting sale item: " . $e->getMessage(), [
                        'product' => $item['product']['name'],
                        'sale_data' => $saleData
                    ]);
                }
            }
            
            // Store results in metadata
            $metadata['sale_results'] = $results;
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Generate confirmation message
            $confirmationMessage = "Sale recorded " . ($allSuccess ? "successfully!" : "with some errors.") . "\n\n";
            
            foreach ($results as $result) {
                $confirmationMessage .= $result['product'] . ": " . 
                    ($result['success'] ? "Success" : "Failed - " . $result['error']) . "\n";
            }
            
            $confirmationMessage .= "\nThank you. Send '1' to record another sale.";
            
            // Send confirmation to user
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                $confirmationMessage
            );
            
            $conversation->status = 'completed';
            $conversation->completed_at = now();
            $conversation->save();
            
            return $allSuccess;
        } catch (\Exception $e) {
            Log::error("Error submitting sale: " . $e->getMessage());
            
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Error submitting sale: " . $e->getMessage() . "\nPlease try again."
            );
            
            return false;
        }
    }
    
    /**
     * Process stock product selection
     */
    private function processStockProductSelection($conversation, $message)
    {
        $metadata = $conversation->metadata;
        $products = $metadata['products'];

        $selection = (int)trim($message) - 1;

        if ($selection < 0 || $selection >= count($products)) {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please choose a number from the list."
            );
            return false;
        }

        // Store selected product
        $metadata['selected_product'] = $products[$selection];
        $metadata['step'] = 'stock_green_product';
        $conversation->metadata = $metadata;
        $conversation->save();

        // Advance to green product selection prompt
        $nextPrompt = Prompt::where('active', true)
            ->where('title', 'Stock Check Green Product')
            ->first();
            
        if ($nextPrompt) {
            $conversation->current_prompt_id = $nextPrompt->id;
            $conversation->save();
        }
        
        // Ask about green product
        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            "Is this a green product?\n1. Yes\n2. No"
        );
        
        return true;
    }

    /**
     * Process stock green product selection and display stock availability
     */
    private function processStockGreenProduct($conversation, $message)
    {
        $selection = trim($message);
        
        if ($selection !== "1" && $selection !== "2") {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid selection. Please reply with 1 for Yes or 2 for No."
            );
            return false;
        }
        
        $isGreen = ($selection === "1");
        
        // Check stock availability
        try {
            $metadata = $conversation->metadata;
            $productId = $metadata['selected_product']['_id'];
            $productName = $metadata['selected_product']['name'] . " " . $metadata['selected_product']['type'];
            
            $stockInfo = $this->endevStovesService->checkStockAvailability($productId, $isGreen);
            
            // Format stock availability message
            $greenLabel = $isGreen ? "Green" : "Non-Green";
            $message = "Stock availability for *{$productName}* ({$greenLabel}):\n";
            
            if (isset($stockInfo['is_fallback']) && $stockInfo['is_fallback']) {
                // Error occurred during stock check
                $message .= "Status: Unable to check stock at this time\n" .
                          "Please try again later or contact support.\n\n";
            } else if ($stockInfo['available'] && $stockInfo['quantity'] > 0) {
                // Stock is available
                $message .= "Available: Yes\n" .
                           "Quantity: {$stockInfo['quantity']} units\n\n";
            } else {
                // No stock available
                $message .= "Available: No\n" .
                           "Currently out of stock\n\n";
            }
            
            $message .= "Send 1 to check another product or 2 to go back to main menu.";
            
            // Update conversation state for next action
            $metadata['step'] = 'stock_check_result';
            $conversation->metadata = $metadata;
            $conversation->save();
            
            // Send the stock availability information
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                $message
            );
            
            return true;
        } catch (\Exception $e) {
            Log::error("Error checking stock: " . $e->getMessage());
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Error checking stock: " . $e->getMessage() . "\nPlease try again."
            );
            
            // Mark conversation as abandoned
            $conversation->status = 'abandoned';
            $conversation->save();
            
            return false;
        }
    }

    /**
     * Process stock check result - handle user choice after seeing stock info
     */
    private function processStockCheckResult($conversation, $message)
    {
        $option = trim($message);
        
        if ($option === "1") {
            // Check another product
            return $this->fetchProductsForStockCheck($conversation);
        } else if ($option === "2") {
            // Go back to main menu
            $startingPrompt = Prompt::where('active', true)
                ->where('title', 'Sales Menu')
                ->whereJsonContains('metadata->is_sales_flow', true)
                ->first();
                
            if (!$startingPrompt) {
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "Sorry, something went wrong. Please try again later."
                );
                return false;
            }
            
            // Start a new conversation with the main menu
            $conversation->status = 'completed';
            $conversation->completed_at = now();
            $conversation->save();
            
            $this->startSalesConversation($conversation->client);
            return true;
        } else {
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Invalid option. Please send 1 to check another product or 2 to go back to main menu."
            );
            return false;
        }
    }
    
    /**
     * Reset the conversation to the main menu
     *
     * @param Conversation $conversation
     * @return void
     */
    private function resetToMainMenu(Conversation $conversation): void
    {
        // Find the main menu prompt
        $mainMenuPrompt = Prompt::where('active', true)
            ->where('title', 'Sales Menu')
            ->whereJsonContains('metadata->is_sales_flow', true)
            ->first();
        
        if (!$mainMenuPrompt) {
            Log::error('Main menu prompt not found', ['conversation_id' => $conversation->id]);
            return;
        }
        
        // Update conversation with main menu prompt
        $conversation->update([
            'current_prompt_id' => $mainMenuPrompt->id,
            'metadata' => ['step' => 'initial']
        ]);
        
        // Send the main menu prompt to the client
        $this->twilioService->sendWhatsAppMessage(
            $conversation->client->phone,
            $mainMenuPrompt->content
        );
    }
    
    /**
     * Go back to the previous step in the conversation flow
     *
     * @param Conversation $conversation
     * @return void
     */
    private function goBackToPreviousStep(Conversation $conversation): void
    {
        $metadata = $conversation->metadata ?: [];
        $currentStep = $metadata['step'] ?? 'initial';
        
        // If we're already at initial, nothing to go back to
        if ($currentStep === 'initial') {
            return;
        }
        
        // Get the previous step from our navigation map
        $previousStep = $this->navigationSteps[$currentStep] ?? 'initial';
        
        Log::info('Navigating back', [
            'from_step' => $currentStep,
            'to_step' => $previousStep,
            'conversation_id' => $conversation->id
        ]);
        
        // Update the step in metadata
        $metadata['step'] = $previousStep;
        $conversation->metadata = $metadata;
        $conversation->save();
        
        // Handle the navigation based on the previous step
        if ($previousStep === 'initial') {
            // Going back to main menu
            $this->resetToMainMenu($conversation);
        } else if ($previousStep === 'staff_selection') {
            // Going back to staff selection
            $this->fetchAndDisplayStaff($conversation);
        } else if ($previousStep === 'customer_selection') {
            // Display customer list
            try {
                $customers = $this->endevStovesService->fetchCustomers();
                
                // Format customer list
                $customerList = "Select a customer:\n";
                foreach ($customers as $index => $customer) {
                    $customerList .= ($index + 1) . ". " . $customer['name'] . "\n";
                }
                $customerList .= (count($customers) + 1) . ". Create New Customer\n\n";
                $customerList .= "0 - Go back\n00 - Main menu";
                
                // Store customers in metadata
                $metadata['customers'] = $customers;
                $conversation->metadata = $metadata;
                $conversation->save();
                
                // Update prompt
                $nextPrompt = Prompt::where('active', true)
                    ->where('title', 'Customer Selection')
                    ->first();
                    
                if ($nextPrompt) {
                    $conversation->current_prompt_id = $nextPrompt->id;
                    $conversation->save();
                }
                
                // Send customer list
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    $customerList
                );
            } catch (\Exception $e) {
                Log::error("Error fetching customers: " . $e->getMessage());
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    "Sorry, we couldn't retrieve the customer list. Please try again."
                );
            }
        } else if ($previousStep === 'date_selection') {
            // Prompt for date
            $today = date('d/m/Y');
            
            // Update prompt
            $nextPrompt = Prompt::where('active', true)
                ->where('title', 'Sale Date')
                ->first();

            if ($nextPrompt) {
                $conversation->current_prompt_id = $nextPrompt->id;
                $conversation->save();
            }
            
            $this->twilioService->sendWhatsAppMessage(
                $conversation->client->phone,
                "Enter sale date (DD/MM/YYYY) or press 1 to use today's date ($today):\n\n0 - Go back\n00 - Main menu"
            );
        } else if ($previousStep === 'product_selection') {
            // Going back to product selection
            $this->fetchAndDisplayProducts($conversation);
        } else {
            // For other steps, try to find the prompt by title
            $stepWords = explode('_', $previousStep);
            $stepWords = array_map('ucfirst', $stepWords);
            $promptTitle = implode(' ', $stepWords);
            
            $previousPrompt = Prompt::where('active', true)
                ->where('title', $promptTitle)
                ->first();
                
            if ($previousPrompt) {
                $conversation->current_prompt_id = $previousPrompt->id;
                $conversation->save();
                
                // Send the prompt content
                $this->twilioService->sendWhatsAppMessage(
                    $conversation->client->phone,
                    $previousPrompt->content
                );
            } else {
                // If we can't find a prompt, just go back to main menu
                $this->resetToMainMenu($conversation);
            }
        }
    }
}
