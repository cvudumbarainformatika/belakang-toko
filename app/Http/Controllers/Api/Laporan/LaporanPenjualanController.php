<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\OrderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanPenjualanController extends Controller
{
    public function getData(){
        $awal=request('tglawal', 'Y-m-d');
        $akhir=request('tglakhir', 'Y-m-d');
        $data = HeaderPenjualan::whereBetween('header_penjualans.tgl', [$awal. ' 00:00:00', $akhir. ' 23:59:59'])
        ->leftJoin('pelanggans', 'pelanggans.id', '=', 'header_penjualans.pelanggan_id')
        ->leftJoin('users', 'users.id', '=', 'header_penjualans.sales_id')
        ->when(request('sales'), function($x) {
            $x->where('header_penjualans.sales_id', request('sales'));
        })
        ->when(request('jnsbayar'), function($x) {
            $x->where('header_penjualans.flag', request('jnsbayar'));
        })
        ->when(request('q'), function($x) {
            $x->where('header_penjualans.no_penjualan', 'like', '%' . request('q') . '%')
            ->orWhere('header_penjualans.tgl', 'like', '%' . request('q') . '%')
            ->orWhere('pelanggans.nama', 'like', '%' . request('q') . '%')
            ->orWhere('users.nama', 'like', '%' . request('q') . '%');
        })
        ->select(
            'header_penjualans.*',
            'pelanggans.nama as pelanggan',
            'users.nama as namasales',
            'users.jabatan',
        )
        ->with('detail',function($query){
            $query->join('barangs', 'barangs.kodebarang', '=', 'detail_penjualans.kodebarang')
            ->select(
                'detail_penjualans.*',
                'barangs.namabarang',
                'barangs.kategori',
                'barangs.satuan_k',
                'barangs.satuan_b',
            );
        })
        ->groupBy('header_penjualans.no_penjualan')
        ->get();
        return new JsonResponse($data);
    }
}
