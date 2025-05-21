<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuPrompt = Prompt::create([
            'title' => 'Sales Menu',
            'content' => "Welcome to EndevStoves Sales System!\n\nSelect an option:\n1. Record a Sale",
            'type' => 'multiple_choice',
            'metadata' => [
                'options' => ['1' => 'Record a Sale'],
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 1,
        ]);

        // Product selection prompt
        $productPrompt = Prompt::create([
            'title' => 'Product Selection',
            'content' => "Select a product:",
            'type' => 'multiple_choice',
            'metadata' => [
                'step' => 'product_selection',
                'is_sales_flow' => true,
                'fetch_products' => true
            ],
            'active' => true,
            'order' => 2,
        ]);

        // Customer selection prompt
        $customerPrompt = Prompt::create([
            'title' => 'Customer Selection',
            'content' => "Select a customer:",
            'type' => 'multiple_choice',
            'metadata' => [
                'step' => 'customer_selection',
                'is_sales_flow' => true,
                'fetch_customers' => true
            ],
            'active' => true,
            'order' => 3,
        ]);

        // Staff selection prompt
        $staffPrompt = Prompt::create([
            'title' => 'Staff Selection',
            'content' => "Select a staff member:",
            'type' => 'multiple_choice',
            'metadata' => [
                'step' => 'staff_selection',
                'is_sales_flow' => true,
                'fetch_staff' => true
            ],
            'active' => true,
            'order' => 4,
        ]);

        // Date prompt
        $datePrompt = Prompt::create([
            'title' => 'Sale Date',
            'content' => "Enter sale date (DD/MM/YYYY) or press 1 to use today's date:",
            'type' => 'text',
            'metadata' => [
                'step' => 'date_selection',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 5,
        ]);

        // Quantity prompt
        $quantityPrompt = Prompt::create([
            'title' => 'Quantity',
            'content' => "Enter quantity:",
            'type' => 'numeric',
            'metadata' => [
                'step' => 'quantity',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 6,
        ]);

        // Unit price prompt
        $unitPricePrompt = Prompt::create([
            'title' => 'Unit Price',
            'content' => "Enter unit price:",
            'type' => 'numeric',
            'metadata' => [
                'step' => 'unit_price',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 7,
        ]);

        // Green product prompt
        $greenPrompt = Prompt::create([
            'title' => 'Green Product',
            'content' => "Is this a green product sale?\n1. Yes\n2. No",
            'type' => 'yes_no',
            'metadata' => [
                'step' => 'green_product',
                'is_sales_flow' => true,
                'options' => ['1' => 'Yes', '2' => 'No']
            ],
            'active' => true,
            'order' => 8,
        ]);

        // Credit sale prompt
        $creditPrompt = Prompt::create([
            'title' => 'Credit Sale',
            'content' => "Is this a credit sale?\n1. Yes\n2. No",
            'type' => 'yes_no',
            'metadata' => [
                'step' => 'credit_sale',
                'is_sales_flow' => true,
                'options' => ['1' => 'Yes', '2' => 'No']
            ],
            'active' => true,
            'order' => 9,
        ]);

        // Deposit prompt (only shown for credit sales)
        $depositPrompt = Prompt::create([
            'title' => 'Deposit',
            'content' => "Enter deposit amount:",
            'type' => 'numeric',
            'metadata' => [
                'step' => 'deposit',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 10,
        ]);

        // Confirmation prompt
        $confirmationPrompt = Prompt::create([
            'title' => 'Confirmation',
            'content' => "Please confirm the sale details:\n\nReply:\n1. Confirm and submit\n2. Cancel",
            'type' => 'multiple_choice',
            'metadata' => [
                'step' => 'confirmation',
                'is_sales_flow' => true,
                'options' => ['1' => 'Confirm', '2' => 'Cancel']
            ],
            'active' => true,
            'order' => 11,
        ]);

        // New customer name prompt
        $customerNamePrompt = Prompt::create([
            'title' => 'New Customer Name',
            'content' => "Creating a new customer. Please enter customer name:",
            'type' => 'text',
            'metadata' => [
                'step' => 'new_customer_name',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 12,
        ]);

        // New customer phone prompt
        $customerPhonePrompt = Prompt::create([
            'title' => 'New Customer Phone',
            'content' => "Enter customer phone number:",
            'type' => 'text',
            'metadata' => [
                'step' => 'new_customer_phone',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 13,
        ]);

        // New customer location prompt
        $customerLocationPrompt = Prompt::create([
            'title' => 'New Customer Location',
            'content' => "Enter customer location:",
            'type' => 'text',
            'metadata' => [
                'step' => 'new_customer_location',
                'is_sales_flow' => true
            ],
            'active' => true,
            'order' => 14,
        ]);

        // Set up the flow relationships
        $menuPrompt->next_prompt_id = $productPrompt->id;
        $menuPrompt->save();

        $productPrompt->next_prompt_id = $customerPrompt->id;
        $productPrompt->save();

        $customerPrompt->next_prompt_id = $staffPrompt->id;
        $customerPrompt->save();

        $staffPrompt->next_prompt_id = $datePrompt->id;
        $staffPrompt->save();

        $datePrompt->next_prompt_id = $quantityPrompt->id;
        $datePrompt->save();

        $quantityPrompt->next_prompt_id = $unitPricePrompt->id;
        $quantityPrompt->save();

        $unitPricePrompt->next_prompt_id = $greenPrompt->id;
        $unitPricePrompt->save();

        $greenPrompt->next_prompt_id = $creditPrompt->id;
        $greenPrompt->save();

        $creditPrompt->next_prompt_id = $depositPrompt->id;
        $creditPrompt->save();

        $depositPrompt->next_prompt_id = $confirmationPrompt->id;
        $depositPrompt->save();

        // Special branching for new customer flow
        $customerNamePrompt->next_prompt_id = $customerPhonePrompt->id;
        $customerNamePrompt->save();

        $customerPhonePrompt->next_prompt_id = $customerLocationPrompt->id;
        $customerPhonePrompt->save();

        $customerLocationPrompt->next_prompt_id = $staffPrompt->id;
        $customerLocationPrompt->save();
    }
}
