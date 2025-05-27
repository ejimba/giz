<?php

use App\Http\Controllers\DebugConversationController;
use App\Http\Controllers\TwilioWebhookController;
use Illuminate\Support\Facades\Route;

Route::any('/', function () {
    return redirect(route('filament.admin.auth.login'));
});
Route::any('/login', function () {
    return redirect(route('filament.admin.auth.login'));
})->name('login');

Route::post('/webhooks/twilio/incoming', [TwilioWebhookController::class, 'handleIncomingMessage']);
Route::post('/webhooks/twilio/status', [TwilioWebhookController::class, 'handleStatusCallback']);

Route::prefix('debug/conversation')->group(function () {
    Route::get('state', [DebugConversationController::class, 'showState']);
    Route::post('update-step', [DebugConversationController::class, 'updateStep']);
    Route::post('reset', [DebugConversationController::class, 'resetConversation']);
    Route::post('test', [DebugConversationController::class, 'testMessage']);
});