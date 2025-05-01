<?php

namespace App\Http\Controllers\Api\Transaksi\Retur;

use App\Http\Controllers\Controller;
use App\Models\KeteranganPelanggan;
use App\Models\Pelanggan;
use App\Models\Transaksi\Penjualan\DetailReturPenjualan;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\Transaksi\Penjualan\HeaderReturPenjualan;
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
                'detailRetur' => function ($q) {
                    $q->select(
                        'status',
                        'detail_retur_penjualans.no_penjualan',
                        'kodebarang',
                        'harga_jual',
                        DB::raw('sum(jumlah) as jumlah'),
                        DB::raw('sum(subtotal) as subtotal'),
                    )
                        ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                        ->groupBy('kodebarang', 'detail_retur_penjualans.no_penjualan')
                    ;
                },
                'draftRetur' => function ($q) {
                    $q->where('status', '');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page') ?? 10);
        $data['data'] = collect($raw)['data'];
        $data['meta'] = collect($raw)->except('data');

        return new JsonResponse($data);
    }
    public function store(Request $request)
    {
        $request->validate([
            'no_penjualan' => 'required',
            'harga_jual' => 'required',
            'kodebarang' => 'required',
            'retur' => 'required',
        ]);
        DB::beginTransaction();
        try {
            $strNo = explode('-', $request->no_penjualan);
            $count = HeaderReturPenjualan::where('no_penjualan', $request->no_penjualan)->count();
            if ((int)$count > 0) {
                $headRetur = HeaderReturPenjualan::where('no_penjualan', $request->no_penjualan)->where('status', '')->first();
                if (!$headRetur) {
                    return new JsonResponse([
                        'message' => 'Data Tidak Ditemukan',
                    ], 410);
                }
                $noretur = $headRetur->no_retur;
            } else {
                $noretur = $strNo[0] . '-' . str_pad($count + 1, 2, '0', STR_PAD_LEFT) . '-' . date('ymd') . '-RTR';
                $headRetur = HeaderReturPenjualan::create([
                    'no_retur' => $noretur,
                    'no_penjualan' => $request->no_penjualan,
                    'tgl' => date('Y-m-d H:i:s'),
                ]);
            }

            // jumlah retur di ambil dari detail penjualan
            $headRetur->detail()->updateOrCreate(
                [
                    'no_penjualan' => $request->no_penjualan,
                    'kodebarang' => $request->kodebarang,
                ],
                [
                    'harga_jual' => $request->harga_jual,
                    'detail_penjualan_id' => $request->id,
                    'jumlah' => $request->retur,
                    'subtotal' => $request->harga_jual * $request->retur,
                ]
            );
            $total = DetailReturPenjualan::where('header_retur_penjualan_id', $headRetur->id)->sum('subtotal');
            if ($total > 0) {
                $headRetur->update([
                    'total' => $total,
                ]);
            }

            DB::commit();
            $pj = HeaderPenjualan::where('no_penjualan', $request->no_penjualan)->with([
                'pelanggan',
                'sales',
                'detail.masterBarang',
                'keterangan',
                'detailRetur' => function ($q) {
                    $q->select(
                        'status',
                        'detail_retur_penjualans.no_penjualan',
                        'kodebarang',
                        'harga_jual',
                        DB::raw('sum(jumlah) as jumlah'),
                        DB::raw('sum(subtotal) as subtotal'),
                    )
                        ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                        ->groupBy('kodebarang', 'detail_retur_penjualans.no_penjualan')
                    ;
                },
                'draftRetur' => function ($q) {
                    $q->where('status', '');
                }
            ])->first();
            return new JsonResponse([
                'message' => 'Data berhasil disimpan',
                'data' => $request->all(),
                'pj' => $pj,
                'noretur' => $noretur,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Data gagal disimpan',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTrace(),
            ], 410);
        }
    }

    public function selesai(Request $request)
    {
        $noretur = $request->no_retur;
        $headRetur = HeaderReturPenjualan::where('no_retur', $noretur)->where('status', '')->first();
        if (!$headRetur) {
            return new JsonResponse([
                'message' => 'Data tidak ditemukan, mungkin sudah selesai',
            ], 410);
        }
        $no_penjualan = $headRetur->no_penjualan;

        $pj = HeaderPenjualan::where('no_penjualan', $no_penjualan)->with([
            'pelanggan',
            'sales',
            'detail.masterBarang',
            'keterangan',
            'detailRetur' => function ($q) {
                $q->select(
                    'status',
                    'detail_retur_penjualans.no_penjualan',
                    'kodebarang',
                    'harga_jual',
                    DB::raw('sum(jumlah) as jumlah'),
                    DB::raw('sum(subtotal) as subtotal'),
                )
                    ->leftJoin('header_retur_penjualans', 'header_retur_penjualans.id', '=', 'detail_retur_penjualans.header_retur_penjualan_id')
                    ->groupBy('kodebarang', 'detail_retur_penjualans.no_penjualan')
                ;
            },
            'draftRetur' => function ($q) {
                $q->where('status', '');
            }
        ])->first();


        return new JsonResponse([
            'message' => 'Data berhasil disimpan',
            'data' => $request->all(),
            'pj' => $pj,
        ], 200);
    }
}
