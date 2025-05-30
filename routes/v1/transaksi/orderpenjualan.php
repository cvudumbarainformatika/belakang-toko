<?php

use App\Models\OrderPenjualan;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'orderpenjualan'
], function () {
    Route::get('/list', [OrderPenjualan::class, 'index']);
});
