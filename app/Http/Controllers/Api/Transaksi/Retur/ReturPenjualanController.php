<?php

namespace App\Http\Controllers\Api\Transaksi\Retur;

use App\Http\Controllers\Controller;
use App\Models\KeteranganPelanggan;
use App\Models\Pelanggan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturPenjualanController extends Controller
{
    //
    public function index()
    {

        $from = request('from') ? request('from') : date('Y-m-01');
        $to = request('to') ? request('to') : date('Y-m-d');
        $q = request('q') ?? false;
        $pelangganId = [];
        $salesId = [];
        $keteranganId = [];
        if ($q) {
            $pelangganId = Pelanggan::select('id')->where('nama', 'LIKE', '%' . $q . '%')->pluck('id')->toArray();
            $salesId = User::select('id')->where('nama', 'LIKE', '%' . $q . '%')->where('jabatan', 'Sales')->pluck('id')->toArray();
            $keteranganId = KeteranganPelanggan::select('header_penjualan_id')->where('nama', 'LIKE', '%' . $q . '%')->pluck('header_penjualan_id')->toArray();
        }
        $raw = HeaderPenjualan::when($q, function ($x) use ($q) {
            $x->where('no_penjualan', 'LIKE', '%' . $q . '%');
        })
            ->when($pelangganId, function ($x) use ($pelangganId) {
                $x->orWhereIn('pelanggan_id', $pelangganId);
            })
            ->when($salesId, function ($x) use ($salesId) {
                $x->orWhereIn('sales_id', $salesId);
            })
            ->when($keteranganId, function ($x) use ($keteranganId) {
                $x->orWhereIn('id', $keteranganId);
            })

            ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereIn('flag', ['2', '3', '4', '5'])
            ->with([
                'pelanggan',
                'sales',
                'detail.masterBarang',
                'keterangan',
                'detailFifo' => function ($q) {
                    $q->select(
                        'no_penjualan',
                        'kodebarang',
                        'harga_jual',
                        DB::raw('sum(jumlah) as jumlah'),
                        DB::raw('sum(subtotal) as subtotal'),
                        DB::raw('sum(diskon) as diskon'),
                    )
                        ->groupBy('kodebarang', 'no_penjualan')
                        ->with(['masterBarang'])
                    ;
                },
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page') ?? 10);
        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');

        return new JsonResponse($data);
    }
}
