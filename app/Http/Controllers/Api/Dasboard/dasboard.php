<?php

namespace App\Http\Controllers\Api\Dasboard;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class dasboard extends Controller
{
    public function listpenjualand()
    {
        $thn =(int) date('Y');
        $thnx = date('Y')-1;

        $data = HeaderPenjualan::select(DB::raw('month(header_penjualans.tgl) as bulan'),DB::raw('year(header_penjualans.tgl) as tahun'),DB::raw('SUM(detail_penjualans.subtotal) as subtotal'))
        ->join('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
        ->groupBy(DB::raw('YEAR(header_penjualans.tgl)'),DB::raw('month(header_penjualans.tgl)'))
        ->whereYear('tgl', $thn)->orWhereYear('tgl', $thnx)->get();
        return new JsonResponse([
            'tahun' =>'Penjualan '. $thn,
            'tahunx' =>'Penjualan '. $thnx,
            'data' => $data,
        ]);
    }
}
