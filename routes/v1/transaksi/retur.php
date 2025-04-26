<?php

use App\Http\Controllers\Api\Transaksi\Retur\ReturPenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/retur'
], function () {
    Route::get('/list-penjualan', [ReturPenjualanController::class, 'index']);
});
