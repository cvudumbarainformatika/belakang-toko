<?php

use App\Http\Controllers\Api\Transaksi\Pengembalian\PengembalianBarangController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        'middleware' => 'auth:api',
        'prefix' => 'transaksi/pengembalianbarang'
    ],
    function () {
        Route::get('/get', [PengembalianBarangController::class, 'index']);
        Route::post('/store', [PengembalianBarangController::class, 'store']);
        Route::get('/getbyid/{id}', [PengembalianBarangController::class, 'show']);
    }
);
