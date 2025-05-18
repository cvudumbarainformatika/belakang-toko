<?php

use App\Http\Controllers\Api\v2\Order\OrderPenjualanController;
use App\Http\Controllers\Api\v2\Product\ProductController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'order'
], function () {

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/penjualan', [OrderPenjualanController::class, 'orderPenjualan']);
    });
});


