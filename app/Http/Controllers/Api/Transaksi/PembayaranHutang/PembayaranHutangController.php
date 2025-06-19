<?php

namespace App\Http\Controllers\Api\Transaksi\PembayaranHutang;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Pembayaranhutang\pembayaranhutang_h;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranHutangController extends Controller
{
    public function index()
    {
        $data = pembayaranhutang_h::all();
        return new JsonResponse($data);
    }
}
