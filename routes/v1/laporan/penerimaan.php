<?php

use App\Http\Controllers\Api\Laporan\LaporanPenerimaanController;
use Illuminate\Support\Facades\Route;

Route::group([
    // 'middleware' => 'auth:api',
    'prefix' => 'laporan/penerimaan'
], function () {
    Route::get('/getdata', [LaporanPenerimaanController::class, 'getData']);

});
