<?php

use App\Http\Controllers\Api\Transaksi\OrderPenjualan\OrderPenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'orderpenjualan'
], function () {
    Route::get('/list', [OrderPenjualanController::class, 'index']);
});
