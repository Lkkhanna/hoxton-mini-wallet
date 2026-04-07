<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransferController;

/*
|--------------------------------------------------------------------------
| API Routes - Mini Wallet & Ledger System
|--------------------------------------------------------------------------
*/

// ─── Account Routes ─────────────────────────────────────────────
Route::prefix('accounts')->group(function () {
    Route::get('/', [AccountController::class, 'index']);           // List all accounts
    Route::post('/', [AccountController::class, 'store']);          // Create account
    Route::get('{account_id}/balance', [AccountController::class, 'balance']);        // Get balance
    Route::get('{account_id}/transactions', [AccountController::class, 'transactions']); // Transaction history
});

// ─── Transfer Routes ────────────────────────────────────────────
Route::post('/transfers', [TransferController::class, 'store']);    // Transfer money
