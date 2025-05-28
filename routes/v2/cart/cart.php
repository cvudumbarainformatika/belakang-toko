<?php

use App\Http\Controllers\Api\v2\Product\CartController;
use Illuminate\Support\Facades\Route;

// Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
//     Route::get('/', [CartController::class, 'index']);
//     Route::post('/', [CartController::class, 'store']);
//     Route::delete('/destroy-all', [CartController::class, 'destroyAllCart']);
//     Route::put('/{barang}', [CartController::class, 'update']);
//     Route::delete('/{barang}', [CartController::class, 'destroy']);
// });

Route::group([
    'prefix' => 'cart',
    'middleware' => ['web', 'auth:sanctum'],
], function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/', [CartController::class, 'store']);
    Route::delete('/destroy-all', [CartController::class, 'destroyAllCart']);
    Route::put('/{barang}', [CartController::class, 'update']);
    Route::delete('/{barang}', [CartController::class, 'destroy']);
});