<?php

use App\Http\Controllers\Api\Dasboard\dasboard;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:api',
    'prefix' => 'dasboard'
], function () {
    Route::get('/penjualanbulanan', [dasboard::class, 'listpenjualand']);
});
