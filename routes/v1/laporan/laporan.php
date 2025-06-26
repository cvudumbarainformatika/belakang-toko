<?php

use App\Http\Controllers\Api\Laporan\LaporanPenerimaanController;
use App\Http\Controllers\Api\Laporan\LaporanPenjualanController;
use Illuminate\Support\Facades\Route;

Route::group([
    // 'middleware' => 'auth:api',
    'prefix' => 'laporan/penerimaan'
], function () {
    // Penerimaan
    Route::get('/getdata', [LaporanPenerimaanController::class, 'getData']);

    //Penjualan
    Route::get('/getpenjualan', [LaporanPenjualanController::class, 'getData']);

});
