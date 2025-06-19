<?php

use App\Http\Controllers\Api\Transaksi\PembayaranHutang\PembayaranHutangController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'transaksi/pembayaranhutang'
], function () {    Route::get('/list', [PembayaranHutangController::class, 'index']);
});
