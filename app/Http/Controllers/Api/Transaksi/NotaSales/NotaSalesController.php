<?php

namespace App\Http\Controllers\Api\Transaksi\NotaSales;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\NotaSales\notasales_h;
use App\Models\Transaksi\NotaSales\notasales_r;
use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotaSalesController extends Controller
{
    public function list(){

        $from = request('from');
        $to = request('to');

        $data = notasales_h::select('notasales_h.*','users.nama')
        ->leftJoin('users', 'users.id', '=', 'notasales_h.kdsales')
        ->with([
            'rinci' => function($rinci){
                $rinci->with(
                    [
                        'hederpenjualan' => function($hederpenjualan){
                            $hederpenjualan->with(['pelanggan', 'sales']);
                        }
                    ]);
            }
        ])
        ->whereBetween('tgl', [
            $from,
            $to
        ])
        ->when(request('q'), function ($query) {
            $query->where(function($q) {
                $q->where('notasales_h.notrans', 'like', '%' . request('q') . '%')
                  ->orWhere('users.nama', 'like', '%' . request('q') . '%')
                  ->orWhere('notasales_h.keterangan', 'like', '%' . request('q') . '%');
            });
        })
        ->orderBy('id', 'desc')
        ->simplePaginate(request('per_page'));
        return new JsonResponse($data);
    }

    public static function getlistbynotrans($notrans)
    {
       $data = notasales_h::with(
            [
                'rinci' => function($rinci){
                $rinci->with(
                    [
                        'hederpenjualan' => function($hederpenjualan){
                            $hederpenjualan->with(['pelanggan', 'sales']);
                        }
                    ]);
                 }
            ]
        )->where('notrans', $notrans)
        ->orderBy('id', 'desc')
        ->get();
        return $data;
    }

    public function caripiutang()
    {
        if(request('keterangan') === 'Dipinjam'){
           $data = HeaderPenjualan::with([
            'pelanggan',
            'sales',
            'detail' => function ($q) {
                $q->with(['masterBarang']);
            },
            ])
            ->where(function ($q) {
                $q->where('flag_sales', '!=', '1')
                ->orWhereNull('flag_sales');
            })
            ->whereIn('flag', ['2', '3', '7'])
            ->orderBy('tempo', 'asc')
            ->get();
            return new JsonResponse($data);
        }else{
            $data = HeaderPenjualan::leftJoin('notasales_r', 'notasales_r.notaPenjualan', '=', 'header_penjualans.no_penjualan')
            ->leftJoin('notasales_h', 'notasales_h.notrans', '=', 'notasales_r.notrans')
            ->with([
                'pelanggan',
                'sales',
                'detail' => function ($q) {
                    $q->with(['masterBarang']);
                },
                ])
                ->where(function ($q) {
                    $q->where('flag_sales','1');
                })
                ->where('notasales_h.kdsales', request('kdsales'))
                // ->whereIn('flag', ['2', '3', '7'])
                ->orderBy('tempo', 'asc')
                ->get();
                return new JsonResponse($data);
        }

    }

    public function simpan(Request $request)
    {
        if($request->notrans === '' || $request->notrans === null)
        {
            $notrans = date('YmdHis').'/'.$request->kdsales.'/'.$request->keterangan;
        }else{
            $notrans = $request->notrans;
        }
        try {
            DB::beginTransaction();
                $simpan = notasales_h::updateOrCreate(
                    [
                    'notrans' => $notrans,
                    ],
                    [
                    'keterangan' => $request->keterangan,
                    'kdsales' => $request->kdsales,
                    'user' => Auth::id(),
                    'tgl' => date('Y-m-d H:i:s'),
                ]);

                $simpanr = notasales_r::create(
                    [
                    'notrans' => $notrans,
                    'notaPenjualan' => $request->notaPenjualan,
                    'tgljatuhtempo' => $request->tgljatuhtempo,
                    'lamatempo' => $request->lamatempo,
                    'total' => $request->total,
                    'user' => Auth::id(),
                    'terbayar' => $request->terbayar,
                ]
            );
            if($request->keterangan === 'Dipinjam'){
                $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                $flagpenjualan->flag_sales = $request->keterangan === 'Dipinjam' ? '1' : null;
                $flagpenjualan->save();
            }
            DB::commit();
            $hasil = self::getlistbynotrans($notrans);
                return new JsonResponse([
                    'message' => 'Data Berhasil Disimpan',
                    'data' => $hasil,
                    'notrans' => $notrans,
                    'notapenjualan' => $request->notaPenjualan
                ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 410);
        }
    }

    public function hapusrincian(Request $request)
    {
        $nopembayaran = $request->notrans;
        try {
            DB::beginTransaction();
                $data = notasales_r::where('id', request('id'))->delete();
                $flagpenjualan = HeaderPenjualan::where('no_penjualan', $request->notaPenjualan)->first();
                $flagpenjualan->flag_sales = $request->keterangan === 'Dipinjam' ? null : '1';
                $flagpenjualan->save();

            DB::commit();
            $hasil = self::getlistbynotrans($nopembayaran);
             return new JsonResponse([
                'message' => 'Data Berhasil Disimpan',
                'data' => $hasil
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return new JsonResponse([
                'message' => 'Terjadi Kesalahan ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 410);
        }
    }
}
