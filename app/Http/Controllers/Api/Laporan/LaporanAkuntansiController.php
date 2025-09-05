<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Laporan\OpnameHutang;
use App\Models\Laporan\OpnamePendapatan;
use App\Models\Laporan\OpnamePengeluaran;
use App\Models\Laporan\OpnamePenjualan;
use App\Models\Laporan\OpnamePiutang;
use App\Models\Stok\StokOpname;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanAkuntansiController extends Controller
{
    public function Aruskas(){
        $tahun = request('tahun') ?? date('Y');
        $bulan = request('bulan') ?? date('m');

        $from = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d 00:00:00');
        $to   = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->endOfMonth()->format('Y-m-d 23:59:59');

        $saldoAwal = OpnamePendapatan::where('tgl_opname', '<', $from)->sum('nilai')
                - OpnamePengeluaran::where('tgl_opname', '<', $from)->sum('nilai');

        // Kas masuk
        $arusKasMasuk = OpnamePendapatan::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();
        if ($arusKasMasuk->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            // Penjualan Lunas
            $totalLunas = DB::table('header_penjualans')
                ->where('flag', 5)
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
                ->sum('detail_penjualans.subtotal');

            // Penjualan Menggunakan DP (flag = 7)
            $totalDP = DB::table('header_penjualans')
                ->where('flag', 7)
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->sum('bayar');

            $totalCicilan = DB::table('pembayaran_cicilans')
                    ->whereBetween('tgl_bayar', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->sum('jumlah');
            $totalPendapatan = $totalLunas + $totalDP;

            // kalau arusKasMasuk kosong → fallback ke pendapatan penjualan
            $totalMasuk = $totalPendapatan + $totalCicilan;
            $arusKasMasuk = [
                [
                    'akun' => '0001-IN',
                    'keterangan' => 'Pendapatan Penjualan',
                    'total' => $totalPendapatan
                ],
                [
                    'akun' => '0002-IN',
                    'keterangan' => 'Pendapatan dari Cicilan',
                    'total' => $totalCicilan
                ]
            ];
        } else {
            // kalau ada data normal
            $totalMasuk = $arusKasMasuk->sum('total');
        }


        // Kas keluar
        $arusKasKeluar = OpnamePengeluaran::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();
        if ($arusKasKeluar->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            $pembelianBarang = DB::table('penerimaan_h')
                    ->where('kunci', 1)
                    ->where('jenis_pembayaran', 'Cash')
                    ->whereBetween('tgl_faktur', [$startOfMonth, $endOfMonth])
                    ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
                    ->sum('penerimaan_r.subtotalfix');

            $bebankeluar = DB::table('transbeban_headers')
                    ->where('flaging', 1)
                    ->whereBetween('tgl', [$startOfMonth, $endOfMonth])
                    ->leftJoin('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
                    ->sum('transbeban_rincis.subtotal');
            $returpenjualan = DB::table('header_retur_penjualans')
                    ->where('status', 1)
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
                    ->sum('detail_retur_penjualans.subtotal');
            $Pembayaranhutang = DB::table('pembayaran_hutang_h')
                    ->whereBetween('tgl_bayar', [$startOfMonth, $endOfMonth])
                    ->leftJoin('pembayaran_hutang_r', 'pembayaran_hutang_r.notrans', '=', 'pembayaran_hutang_h.notrans')
                    ->sum('pembayaran_hutang_r.total');

            $totalKeluar = $pembelianBarang + $bebankeluar + $returpenjualan + $Pembayaranhutang;
            $arusKasKeluar = [
                [
                    'akun' => '0001-OUT',
                    'keterangan' => 'Pembelian Langsung',
                    'total' => $pembelianBarang
                ],
                [
                    'akun' => '0002-OUT',
                    'keterangan' => 'Beban Pengeluaran',
                    'total' => $bebankeluar
                ],
                [
                    'akun' => '0003-OUT',
                    'keterangan' => 'Retur Penjualan',
                    'total' => $returpenjualan
                ],
                [
                    'akun' => '0004-OUT',
                    'keterangan' => 'Pembayaran Hutang',
                    'total' => $Pembayaranhutang
                ]
            ];
        } else {
                    // kalau ada data normal
                    $totalKeluar = $arusKasKeluar->sum('total');
                }


        $kenaikanKas = $totalMasuk - $totalKeluar;
        $saldoAkhir = $saldoAwal + $kenaikanKas;

        return response()->json([
            'periode' => "$tahun-$bulan",
            'kasmasuk' => $arusKasMasuk,
            'total_masuk' => $totalMasuk,
            'kaskeluar' => $arusKasKeluar,
            'total_keluar' => $totalKeluar,
            'kenaikan_kas' => $kenaikanKas,
            'saldo_awal' => $saldoAwal,
            'saldo_akhir' => $saldoAkhir,
        ]);
    }
    public function Labarugi(){
        $tahun = request('tahun') ?? date('Y');
        $bulan = request('bulan') ?? date('m');

        $from = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d 00:00:00');
        $to   = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->endOfMonth()->format('Y-m-d 23:59:59');

        $penjualan = OpnamePenjualan::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();

        $hpp =  DB::table('header_penjualans')
            ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
            ->sum(DB::raw('(detail_penjualans.jumlah * detail_penjualans.harga_beli)'));

        if ($penjualan->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');

            $penjualanSemua =  DB::table('header_penjualans')
                    ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                    ->leftJoin('detail_penjualans', 'detail_penjualans.no_penjualan', '=', 'header_penjualans.no_penjualan')
                    ->sum('detail_penjualans.subtotal');

            $returpenjualan = DB::table('header_retur_penjualans')
                ->where('status', 1)
                ->whereBetween('tgl', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
                ->sum('detail_retur_penjualans.subtotal');
            $totalPenjualan = $penjualanSemua - $returpenjualan;

            $penjualan = [
                [
                    'akun' => '0001-PNJ',
                    'keterangan' => 'Penjualan Bersih',
                    'total' => $totalPenjualan
                ],
            ];
        } else {
            $totalPenjualan = $penjualan->sum('total');

        }
        $labaKotor = $totalPenjualan - $hpp;

        //     $returpenjualan = DB::table('header_retur_penjualans')
        //         ->where('status', 1)
        //         ->whereBetween('tgl', [$from . ' 00:00:00', $to . ' 23:59:59'])
        //         ->leftJoin('detail_retur_penjualans', 'detail_retur_penjualans.header_retur_penjualan_id', '=', 'header_retur_penjualans.id')
        //         ->sum('detail_retur_penjualans.subtotal');
        //     $totalPenjualan = $penjualanSemua - $returpenjualan;

        // $lastMonth = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->subMonth();
        // $persediaanAwal = StokOpname::whereYear('tgl_opname', $lastMonth->year)
        //     ->whereMonth('tgl_opname', $lastMonth->month)
        //     ->sum(DB::raw('jumlah_k * harga_beli_k'));
        // $persediaanAkhir = StokOpname::whereYear('tgl_opname', $tahun)
        //     ->whereMonth('tgl_opname', $bulan)
        //     ->sum(DB::raw('jumlah_k * harga_beli_k'));

        // $pembelianBarang = DB::table('penerimaan_h')
        //     ->where('kunci', 1)
        //     ->whereBetween('tgl_faktur', [$from, $to])
        //     ->leftJoin('penerimaan_r', 'penerimaan_r.nopenerimaan', '=', 'penerimaan_h.nopenerimaan')
        //     ->sum('penerimaan_r.subtotalfix');
        // $hpp = ($persediaanAwal + $pembelianBarang) - $persediaanAkhir;


        $beban = OpnamePengeluaran::whereBetween('tgl_opname', [$from, $to])
            ->where('akun', '=', '0002-OUT')
            ->groupBy('akun', 'keterangan')
            ->selectRaw('akun, keterangan, SUM(nilai) as total')
            ->get();
          if ($beban->isEmpty()) {
            $startOfMonth = Carbon::parse($from)->startOfMonth()->format('Y-m-d');
            $endOfMonth   = Carbon::parse($to)->endOfMonth()->format('Y-m-d');
            $bebankeluar = DB::table('transbeban_headers')
                    ->where('flaging', 1)
                    ->whereBetween('tgl', [$startOfMonth, $endOfMonth])
                    ->leftJoin('transbeban_rincis', 'transbeban_rincis.notrans', '=', 'transbeban_headers.notrans')
                    ->sum('transbeban_rincis.subtotal');
            $totalBeban = $bebankeluar;
            $beban = [
                    [
                        'akun' => '0002-OUT',
                        'keterangan' => 'Beban Pengeluaran',
                        'total' => $bebankeluar
                    ],
                ];
            } else {
                $totalBeban = $beban->sum('total');
          }

        $labaoperasional = $labaKotor - $totalBeban;
        return response()->json([
            // 'persediaan_awal' => $persediaanAwal,
            // 'persediaan_akhir' => $persediaanAkhir,
            // 'pembelian_barang' => $pembelianBarang,
            'penjualan' => $penjualan,
            'beban' => $beban,
            'total_penjualan' => $totalPenjualan,
            'total_beban' => $totalBeban,
            'hpp' => $hpp,
            'laba_kotor' => $labaKotor,
            'laba_operasional' => $labaoperasional
        ]);
    }

    public function hutangpiutang()
    {
        $tahun = request('tahun') ?? date('Y');
        $bulan = request('bulan') ?? date('m');

        $from = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d 00:00:00');
        $to   = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->endOfMonth()->format('Y-m-d 23:59:59');

        $datahutang = OpnameHutang::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun')
            ->selectRaw('akun, keterangan, SUM(debit) as totaldebit, SUM(kredit) as totalkredit')
            ->get();
        $totalDebithutang = $datahutang->sum('totaldebit');
        $totalKredithutang = $datahutang->sum('totalkredit');
        $totalSisaHutang = $totalDebithutang - $totalKredithutang;


        $datapiutang = OpnamePiutang::whereBetween('tgl_opname', [$from, $to])
            ->groupBy('akun')
            ->selectRaw('akun, keterangan, SUM(debit) as totaldebit, SUM(kredit) as totalkredit')
            ->get();
        $totalDebitpiutang = $datapiutang->sum('totaldebit');
        $totalKreditpiutang = $datapiutang->sum('totalkredit');
        $totalSisapiutang = $totalDebitpiutang - $totalKreditpiutang;

        return response()->json([
            'datahutang' => $datahutang,
            'debithutang' => $totalDebithutang,
            'kredithutang' => $totalKredithutang,
            'sisahutang' => $totalSisaHutang,


            'datapiutang' => $datapiutang,
            'debitpiutang' => $totalDebitpiutang,
            'kreditpiutang' => $totalKreditpiutang,
            'sisapiutang' => $totalSisapiutang,
        ]);
    }
}
