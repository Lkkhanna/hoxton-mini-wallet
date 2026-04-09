<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('accounts')->group(function () {
    Route::get('/', [AccountController::class, 'index']);
    Route::post('/', [AccountController::class, 'store']);
    Route::get('{account}/balance', [AccountController::class, 'balance']);
    Route::get('{account}/transactions', [AccountController::class, 'transactions']);
});

Route::post('/transfers', [TransferController::class, 'store']);
