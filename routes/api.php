<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\MerchantController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Provider\MpesaC2bController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

// Inbound provider callbacks (Safaricom). Path intentionally avoids words Daraja blocks.
Route::prefix('c2b/{routeKey}')->middleware('throttle:provider')->group(function () {
    Route::post('confirmation', [MpesaC2bController::class, 'confirmation'])->name('provider.c2b.confirmation');
    Route::post('validation', [MpesaC2bController::class, 'validation'])->name('provider.c2b.validation');
});

// Merchant REST API
Route::prefix('v1')->middleware('throttle:merchant-api')->group(function () {
    Route::get('me', [MerchantController::class, 'me'])->middleware(AuthenticateApiKey::class);

    Route::middleware(AuthenticateApiKey::class.':accounts:read')->group(function () {
        Route::get('accounts', [AccountController::class, 'index']);
        Route::get('accounts/{id}/balance', [AccountController::class, 'balance']);
    });

    Route::middleware(AuthenticateApiKey::class.':transactions:read')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/reference/{reference}', [TransactionController::class, 'byReference']);
        Route::get('transactions/receipt/{receipt}', [TransactionController::class, 'byReceipt']);
        Route::get('transactions/{id}', [TransactionController::class, 'show']);
    });

    Route::get('reports/collections', [ReportController::class, 'collections'])
        ->middleware(AuthenticateApiKey::class.':reports:read');
});
