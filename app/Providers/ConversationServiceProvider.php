<?php

namespace App\Providers;

use App\Services\Conversation\ConversationService;
use App\Services\Conversation\NavigationService;
use App\Services\Conversation\Handlers\InitialHandler;
use App\Services\Conversation\Handlers\StaffHandler;
use App\Services\Conversation\Handlers\CustomerHandler;
use App\Services\Conversation\Handlers\DateHandler;
use App\Services\Conversation\Handlers\ProductHandler;
use App\Services\Conversation\Handlers\QuantityHandler;
use App\Services\Conversation\Handlers\UnitPriceHandler;
use App\Services\Conversation\Handlers\GreenProductHandler;
use App\Services\Conversation\Handlers\AddMoreHandler;
use App\Services\Conversation\Handlers\CreditSaleHandler;
use App\Services\Conversation\Handlers\DepositHandler;
use App\Services\Conversation\Handlers\ConfirmationHandler;
use App\Services\Conversation\Handlers\StockProductHandler;
use App\Services\Conversation\Handlers\StockGreenHandler;
use App\Services\Conversation\Handlers\StockResultHandler;
use App\Services\Conversation\Handlers\NewCustomerNameHandler;
use App\Services\Conversation\Handlers\NewCustomerPhoneHandler;
use App\Services\Conversation\Handlers\CustomerErrorHandler;
use Illuminate\Support\ServiceProvider;

class ConversationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConversationService::class, function ($app) {
            $handlers = [
                $app->make(InitialHandler::class),
                $app->make(StaffHandler::class),
                $app->make(CustomerHandler::class),
                $app->make(DateHandler::class),
                $app->make(ProductHandler::class),
                $app->make(QuantityHandler::class),
                $app->make(UnitPriceHandler::class),
                $app->make(GreenProductHandler::class),
                $app->make(AddMoreHandler::class),
                $app->make(CreditSaleHandler::class),
                $app->make(DepositHandler::class),
                $app->make(ConfirmationHandler::class),
                
                $app->make(StockProductHandler::class),
                $app->make(StockGreenHandler::class),
                $app->make(StockResultHandler::class),
                
                $app->make(NewCustomerNameHandler::class),
                $app->make(NewCustomerPhoneHandler::class),
                $app->make(CustomerErrorHandler::class),
            ];
            $service = new ConversationService(
                $app->make(NavigationService::class),
                $handlers
            );
            foreach ($handlers as $handler) {
                if (method_exists($handler, 'setConversationService')) {
                    $handler->setConversationService($service);
                }
            }
            return $service;
        });
    }
    
    public function boot(): void
    {
        
    }
}