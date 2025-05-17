<?php

use App\Http\Controllers\Api\Transaksi\Pengembalian\PengembalianBarangController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        'middleware' => 'auth:api',
        'prefix' => 'transaksi'
    ],
    function () {
        Route::get('/pengembalianbarang', [PengembalianBarangController::class, 'index']);
        Route::post('/pengembalianbarang', [PengembalianBarangController::class, 'store']);
        Route::get('/pengembalianbarang/{id}', [PengembalianBarangController::class, 'show']);
    }
);
