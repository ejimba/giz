<?php

namespace App\Services\Conversation;

class Step
{
    public const INITIAL = 'initial';
    public const STAFF_SELECTION = 'staff_selection';
    public const CUSTOMER_SELECTION = 'customer_selection';
    public const DATE_SELECTION = 'date_selection';
    public const PRODUCT_SELECTION = 'product_selection';
    public const QUANTITY = 'quantity';
    public const UNIT_PRICE = 'unit_price';
    public const GREEN_PRODUCT = 'green_product';
    public const ADD_MORE_PRODUCTS = 'add_more_products';
    public const CREDIT_SALE = 'credit_sale';
    public const DEPOSIT = 'deposit';
    public const CONFIRMATION = 'confirmation';
    
    public const STOCK_PRODUCT_SELECTION = 'stock_product_selection';
    public const STOCK_GREEN_PRODUCT = 'stock_green_product';
    public const STOCK_CHECK_RESULT = 'stock_check_result';
    
    public const NEW_CUSTOMER_NAME = 'new_customer_name';
    public const NEW_CUSTOMER_PHONE = 'new_customer_phone';
    public const HANDLE_CUSTOMER_CREATION_ERROR = 'handle_customer_creation_error';
    
    public const NEW_STAFF_NAME = 'new_staff_name';
    public const NEW_STAFF_PHONE = 'new_staff_phone';
    public const HANDLE_STAFF_CREATION_ERROR = 'handle_staff_creation_error';
    
    public const ADD_CUSTOMER_MENU = 'add_customer_menu';
    public const ADD_STAFF_MENU = 'add_staff_menu';
    
    public static function getNavigationMap(): array
    {
        return [
            self::STAFF_SELECTION => self::INITIAL,
            self::CUSTOMER_SELECTION => self::STAFF_SELECTION,
            self::DATE_SELECTION => self::CUSTOMER_SELECTION,
            self::PRODUCT_SELECTION => self::DATE_SELECTION,
            self::QUANTITY => self::PRODUCT_SELECTION,
            self::UNIT_PRICE => self::QUANTITY,
            self::GREEN_PRODUCT => self::UNIT_PRICE,
            self::ADD_MORE_PRODUCTS => self::GREEN_PRODUCT,
            self::CREDIT_SALE => self::ADD_MORE_PRODUCTS,
            self::DEPOSIT => self::CREDIT_SALE,
            self::CONFIRMATION => self::DEPOSIT,

            self::STOCK_PRODUCT_SELECTION => self::INITIAL,
            self::STOCK_GREEN_PRODUCT => self::STOCK_PRODUCT_SELECTION,
            self::STOCK_CHECK_RESULT => self::STOCK_GREEN_PRODUCT,

            self::NEW_CUSTOMER_NAME => self::CUSTOMER_SELECTION,
            self::NEW_CUSTOMER_PHONE => self::NEW_CUSTOMER_NAME,
            self::HANDLE_CUSTOMER_CREATION_ERROR => self::NEW_CUSTOMER_PHONE,
            
            self::ADD_CUSTOMER_MENU => self::INITIAL,
            self::ADD_STAFF_MENU => self::INITIAL,
            
            self::NEW_STAFF_NAME => self::ADD_STAFF_MENU,
            self::NEW_STAFF_PHONE => self::NEW_STAFF_NAME,
            self::HANDLE_STAFF_CREATION_ERROR => self::NEW_STAFF_PHONE,
        ];
    }
}