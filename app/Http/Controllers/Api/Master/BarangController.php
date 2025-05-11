<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Imagebarang;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BarangController extends Controller
{
    public function listbarang()
    {
        // $data = Barang::whereNull('barangs.flaging')
        //     ->when(request('q') !== '' || request('q') !== null, function ($x) {
        //         $x->where('barangs.namabarang', 'like', '%' . request('q') . '%')
        //             ->orWhere('barangs.kodebarang', 'like', '%' . request('q') . '%');
        //     })
        //     ->with('images')
        //     // ->leftJoin('imagebarangs', 'barangs.kodebarang', '=', 'imagebarangs.kodebarang')
        //     // ->select('barangs.*',)
        //     // ->selectSub(function ($query) {
        //     //     $query->from('imagebarangs')
        //     //          ->selectRaw('GROUP_CONCAT(gambar)')
        //     //         ->whereColumn('imagebarangs.kodebarang', 'barangs.kodebarang');
        //     // }, 'gambar_list')
        //     // ->groupBy('barangs.kodebarang')
        //     ->orderBy('barangs.id', 'desc')
        //     ->simplePaginate(request('per_page'));


   // Tentukan tahun dan bulan, default ke saat ini jika tidak ada
    $tahun = request('tahun') ?? Carbon::now()->format('Y');
    $bulan = request('bulan') ?? Carbon::now()->format('m');

    // Hitung awal dan akhir bulan
    $awal = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->startOfMonth()->format('Y-m-d');
    $akhir = Carbon::createFromFormat('Y-m-d', $awal)->endOfMonth()->format('Y-m-d');

    // Hitung awal bulan sebelumnya untuk validasi saldo akhir
    $bulanSebelumnya = Carbon::createFromFormat('Y-m-d', "$tahun-$bulan-01")->subMonth()->startOfMonth()->format('Y-m-d');
    $akhirBulanSebelumnya = Carbon::createFromFormat('Y-m-d', $bulanSebelumnya)->endOfMonth()->format('Y-m-d');

    // Log rentang tanggal untuk debugging
    Log::info('Rentang tanggal', [
        'awal' => $awal,
        'akhir' => $akhir,
        'bulan_sebelumnya' => $bulanSebelumnya,
        'akhir_bulan_sebelumnya' => $akhirBulanSebelumnya,
        'tahun' => $tahun,
        'bulan' => $bulan
    ]);

    $data = Barang::whereNull('barangs.flaging')
        ->when(request('q'), function ($query) {
            $query->where(function ($q) {
                $q->where('barangs.namabarang', 'like', '%' . request('q') . '%')
                  ->orWhere('barangs.kodebarang', 'like', '%' . request('q') . '%');
            });
        })
        ->leftJoin('imagebarangs', function ($join) {
            $join->on('barangs.kodebarang', '=', 'imagebarangs.kodebarang')
                 ->where('imagebarangs.flag_thumbnail', '=', 1);
        })
        ->leftJoin('stoks', function ($join) {
            $join->on('barangs.kodebarang', '=', 'stoks.kdbarang')
                 ->where('stoks.jumlah_k', '!=', 0);
        })
        ->with([
            'rincians',
            'penerimaan' => function ($query) use ($awal, $akhir) {
                $query->join('penerimaan_h', 'penerimaan_h.nopenerimaan', '=', 'penerimaan_r.nopenerimaan')
                      ->whereBetween('penerimaan_h.tgl_faktur', [$awal, $akhir])
                      ->select(
                          'penerimaan_h.tgl_faktur as tanggal',
                          'penerimaan_r.kdbarang',
                          'penerimaan_r.nopenerimaan as notransaksi',
                          'penerimaan_r.jumlah_k as penerimaan',
                          'penerimaan_r.isi',
                          'penerimaan_r.satuan_k',
                          'penerimaan_r.satuan_b'
                      );
            },
            'penjualan' => function ($query) use ($awal, $akhir) {
                $query->join('header_penjualans', 'header_penjualans.no_penjualan', '=', 'detail_penjualan_fifos.no_penjualan')
                      ->join('barangs', 'barangs.kodebarang', '=', 'detail_penjualan_fifos.kodebarang')
                      ->whereIn('header_penjualans.flag', ['2', '3', '4', '5', '7'])
                      ->whereBetween('header_penjualans.tgl', [$awal, $akhir])
                      ->select(
                          'header_penjualans.tgl as tanggal',
                          'detail_penjualan_fifos.kodebarang',
                          'detail_penjualan_fifos.no_penjualan as notransaksi',
                          'detail_penjualan_fifos.jumlah as pengeluaran',
                          'barangs.satuan_k',
                          'barangs.satuan_b',
                          'barangs.isi'
                      );
            },
            'penyesuaian' => function ($query) use ($awal, $akhir) {
                $query->whereBetween('penyesuaians.tgl', [$awal, $akhir])
                      ->select('*');
            },
            'stoks'
        ])
        ->select('barangs.*')
        ->selectRaw('
            GROUP_CONCAT(imagebarangs.gambar) as image,
            GROUP_CONCAT(imagebarangs.flag_thumbnail) as flag_thumbnail,
            COALESCE(SUM(CASE WHEN stoks.jumlah_k != 0 THEN stoks.jumlah_k ELSE 0 END), 0) as stok_kecil,
            COALESCE(ROUND(SUM(CASE WHEN stoks.isi != 0 THEN stoks.jumlah_k / stoks.isi ELSE 0 END), 2), 0) as stok_besar
        ')
        ->when(request('minim_stok'), function ($query) {
            $query->havingRaw('
                CASE
                    WHEN COALESCE(SUM(CASE WHEN stoks.jumlah_k != 0 THEN stoks.jumlah_k ELSE 0 END), 0) <= barangs.minim_stok THEN 1
                    WHEN COALESCE(SUM(CASE WHEN stoks.jumlah_k != 0 THEN stoks.jumlah_k ELSE 0 END), 0) > barangs.minim_stok THEN 2
                END = ?', [request('minim_stok')]
            );
        })
        ->groupBy('barangs.id')
        ->orderBy('barangs.id', 'desc')
        ->simplePaginate(request('per_page'));

    // Transformasi data untuk menambahkan kartustok dan total
   // Transformasi data untuk menambahkan kartustok dan total
    $data->getCollection()->transform(function ($item) use ($awal, $bulanSebelumnya, $akhirBulanSebelumnya) {
        // Log data relasi untuk debugging
        Log::info('Data relasi', [
            'kodebarang' => $item->kodebarang,
            'penerimaan_count' => count($item->penerimaan),
            'penerimaan' => $item->penerimaan->toArray(),
            'penjualan_count' => count($item->penjualan),
            'penjualan' => $item->penjualan->toArray(),
            'penyesuaian_count' => count($item->penyesuaian),
            'penyesuaian' => $item->penyesuaian->toArray(),
        ]);

        // Hitung saldo awal (sebelum rentang tanggal)
        $saldoAwal = Barang::where('barangs.kodebarang', $item->kodebarang)
            ->leftJoin('penerimaan_r', 'penerimaan_r.kdbarang', '=', 'barangs.kodebarang')
            ->leftJoin('penerimaan_h', 'penerimaan_h.nopenerimaan', '=', 'penerimaan_r.nopenerimaan')
            ->leftJoin('detail_penjualan_fifos', 'detail_penjualan_fifos.kodebarang', '=', 'barangs.kodebarang')
            ->leftJoin('header_penjualans', 'header_penjualans.no_penjualan', '=', 'detail_penjualan_fifos.no_penjualan')
            ->leftJoin('penyesuaians', 'penyesuaians.kdbarang', '=', 'barangs.kodebarang')
            ->where(function ($query) use ($awal) {
                $query->where('penerimaan_h.tgl_faktur', '<', $awal)
                    ->orWhereNull('penerimaan_h.tgl_faktur');
            })
            ->where(function ($query) use ($awal) {
                $query->where(function ($subQuery) use ($awal) {
                    $subQuery->where('header_penjualans.tgl', '<', $awal)
                            ->whereIn('header_penjualans.flag', ['2', '3', '4', '5', '7']);
                })->orWhereNull('header_penjualans.tgl');
            })
            ->where(function ($query) use ($awal) {
                $query->where('penyesuaians.tgl', '<', $awal)
                    ->orWhereNull('penyesuaians.tgl');
            })
            ->selectRaw('
                COALESCE(SUM(penerimaan_r.jumlah_k), 0) + COALESCE(SUM(penyesuaians.jumlah_k), 0) -
                COALESCE(SUM(detail_penjualan_fifos.jumlah), 0) as saldo_awal
            ')
            ->first()
            ->saldo_awal ?? 0;

        // Hitung saldo akhir bulan sebelumnya untuk validasi
        $saldoAkhirBulanSebelumnya = Barang::where('barangs.kodebarang', $item->kodebarang)
            ->leftJoin('penerimaan_r', 'penerimaan_r.kdbarang', '=', 'barangs.kodebarang')
            ->leftJoin('penerimaan_h', 'penerimaan_h.nopenerimaan', '=', 'penerimaan_r.nopenerimaan')
            ->leftJoin('detail_penjualan_fifos', 'detail_penjualan_fifos.kodebarang', '=', 'barangs.kodebarang')
            ->leftJoin('header_penjualans', 'header_penjualans.no_penjualan', '=', 'detail_penjualan_fifos.no_penjualan')
            ->leftJoin('penyesuaians', 'penyesuaians.kdbarang', '=', 'barangs.kodebarang')
            ->where(function ($query) use ($akhirBulanSebelumnya) {
                $query->where('penerimaan_h.tgl_faktur', '<=', $akhirBulanSebelumnya)
                    ->orWhereNull('penerimaan_h.tgl_faktur');
            })
            ->where(function ($query) use ($akhirBulanSebelumnya) {
                $query->where(function ($subQuery) use ($akhirBulanSebelumnya) {
                    $subQuery->where('header_penjualans.tgl', '<=', $akhirBulanSebelumnya)
                            ->whereIn('header_penjualans.flag', ['2', '3', '4', '5', '7']);
                })->orWhereNull('header_penjualans.tgl');
            })
            ->where(function ($query) use ($akhirBulanSebelumnya) {
                $query->where('penyesuaians.tgl', '<=', $akhirBulanSebelumnya)
                    ->orWhereNull('penyesuaians.tgl');
            })
            ->selectRaw('
                COALESCE(SUM(penerimaan_r.jumlah_k), 0) + COALESCE(SUM(penyesuaians.jumlah_k), 0) -
                COALESCE(SUM(detail_penjualan_fifos.jumlah), 0) as saldo_akhir
            ')
            ->first()
            ->saldo_akhir ?? 0;

        // Log saldo untuk debugging
        Log::info('Saldo', [
            'kodebarang' => $item->kodebarang,
            'saldo_awal' => $saldoAwal,
            'saldo_akhir_bulan_sebelumnya' => $saldoAkhirBulanSebelumnya,
            'is_consistent' => abs($saldoAwal - $saldoAkhirBulanSebelumnya) < 0.0001
        ]);

        // Gabungkan semua transaksi ke satu array
        $transaksi = [];

        // Tambahkan penerimaan
        foreach ($item->penerimaan as $penerimaan) {
            $transaksi[] = [
                'type' => 'penerimaan',
                'tanggal' => $penerimaan->tanggal,
                'notransaksi' => $penerimaan->notransaksi,
                'debit' => floatval($penerimaan->penerimaan),
                'kredit' => 0,
                'satuan_k' => $penerimaan->satuan_k,
                'satuan_b' => $penerimaan->satuan_b,
                'isi' => floatval($penerimaan->isi),
            ];
        }

        // Tambahkan penjualan
        foreach ($item->penjualan as $penjualan) {
            $transaksi[] = [
                'type' => 'penjualan',
                'tanggal' => $penjualan->tanggal,
                'notransaksi' => $penjualan->notransaksi,
                'debit' => 0,
                'kredit' => floatval($penjualan->pengeluaran),
                'satuan_k' => $penjualan->satuan_k,
                'satuan_b' => $penjualan->satuan_b,
                'isi' => floatval($penjualan->isi),
            ];
        }

        // Tambahkan penyesuaian
        foreach ($item->penyesuaian as $penyesuaian) {
            $debit = floatval($penyesuaian->jumlah_k);
            $kredit = 0;
            // Jika nilai penyesuaian berkurang (negatif) maka menjadi kredit dengan nilai positif
            if ($debit < 0) {
                $kredit = abs($debit);
                $debit = 0;
            }
            $transaksi[] = [
                'type' => 'penyesuaian',
                'tanggal' => $penyesuaian->tgl,
                'notransaksi' => $penyesuaian->nopenyesuaian,
                'debit' => $debit,
                'kredit' => $kredit,
                'satuan_k' => $item->satuan_k,
                'satuan_b' => $item->satuan_b,
                'isi' => floatval($item->isi),
            ];
        }

        // Urutkan transaksi berdasarkan tanggal
        usort($transaksi, function ($a, $b) {
            return strtotime($a['tanggal']) <=> strtotime($b['tanggal']);
        });

        // Buat kartustok
        $kartustok = [];
        $akumulasi_debit = $saldoAwal;
        $akumulasi_kredit = 0;

        // Tambahkan entri saldo awal
        $kartustok[] = [
            'tanggal' => $awal,
            'notransaksi' => 'SALDO AWAL',
            'debit' => floatval($saldoAwal),
            'kredit' => 0,
            'total' => floatval($saldoAwal),
            'satuan_k' => $item->satuan_k ?? '',
            'satuan_b' => $item->satuan_b ?? '',
            'isi' => floatval($item->isi ?? 0),
            'debit_b' => round(floatval($saldoAwal / ($item->isi ?? 1)), 2),
            'kredit_b' => 0,
            'total_b' => round(floatval($saldoAwal / ($item->isi ?? 1)), 2),
        ];

        // Proses transaksi
        foreach ($transaksi as $trx) {
            $akumulasi_debit += $trx['debit'];
            $akumulasi_kredit += $trx['kredit'];
            $total = $saldoAwal + $akumulasi_debit - $akumulasi_kredit;

            $kartustok[] = [
                'tanggal' => $trx['tanggal'],
                'notransaksi' => $trx['notransaksi'],
                'debit' => $trx['debit'],
                'kredit' => $trx['kredit'],
                'total' => floatval($total),
                'satuan_k' => $trx['satuan_k'],
                'satuan_b' => $trx['satuan_b'],
                'isi' => $trx['isi'],
                'debit_b' => round(floatval($trx['debit'] / ($trx['isi'] ?? 1)), 2),
                'kredit_b' => round(floatval($trx['kredit'] / ($trx['isi'] ?? 1)), 2),
                'total_b' => round(floatval($total / ($trx['isi'] ?? 1)), 2),
            ];
        }

        // Log kartustok setelah penggabungan
        Log::info('Kartustok', ['kodebarang' => $item->kodebarang, 'kartustok' => $kartustok]);

        // Hitung total debit dan kredit untuk bulan ini (tidak termasuk saldo awal)
        $totalDebit = array_sum(array_column(array_slice($kartustok, 1), 'debit'));
        $totalKredit = array_sum(array_column(array_slice($kartustok, 1), 'kredit'));
        $saldoAkhir = $saldoAwal + $totalDebit - $totalKredit;

        // Log saldo akhir untuk debugging
        Log::info('Saldo akhir', [
            'kodebarang' => $item->kodebarang,
            'saldo_akhir' => $saldoAkhir
        ]);

        // Tambahkan kartustok dan total ke item
        $item->kartustok = $kartustok;
        $item->total = [
            'saldo_awal' => floatval($saldoAwal),
            'total_debit' => floatval($totalDebit),
            'total_kredit' => floatval($totalKredit),
            'saldo_akhir' => floatval($saldoAkhir),
            'total_debitbesar' => round(floatval($totalDebit / ($item->isi ?? 1)), 2),
            'total_kreditbesar' => round(floatval($totalKredit / ($item->isi ?? 1)), 2),
            'saldo_akhirbesar' => round(floatval($saldoAkhir / ($item->isi ?? 1)), 2),
        ];

        // Hapus relasi asli untuk mengurangi ukuran respons
        unset($item->penerimaan);
        unset($item->penjualan);
        unset($item->penyesuaian);

        return $item;
    });

    return new JsonResponse($data);
    }

    public function simpanbarang(Request $request)
    {
        $messages = [
            'rincians.*.gambar.max' => 'Ukuran Foto Tidak Boleh Lebih dari 2MB.',
            'rincians.*.gambar.image' => 'File harus berupa gambar.',
            'namabarang.required' => 'Nama Barang Wajib diisi.',
            'hargajual1.numeric' => 'Harga Pengguna Harus Angka.',
            'hargajual2.numeric' => 'Harga Toko Harus Angka.',
            'hargabeli.numeric' => 'Harga Beli Harus Angka.'
        ];

        $request->validate([
            'rincians.*.gambar' => 'nullable|image|max:2048', // Maksimal 2MB
            'namabarang' => 'required',
            'hargajual1' => 'nullable|numeric',
            'hargajual2' => 'nullable|numeric',
            'hargabeli' => 'nullable|numeric',

        ], $messages);

        if ($request->kodebarang === '' || $request->kodebarang === null)
        {
             DB::select('call kodebarang(@nomor)');
            $x = DB::table('counter')->select('kodebarang')->get();
            $no = $x[0]->kodebarang;

            // $cek = Barang::count();
            // $total = (int) $cek + (int) 1;
            $kodebarang = FormatingHelper::matkdbarang($no, 'BRG');
        } else {
            $kodebarang = $request->kodebarang;
        }

        $namagabung = $request->brand . ' ' . $request->ukuran . ' ' . $request->namagabung . ' ' . $request->kualitas;
        $simpan = Barang::updateOrCreate(
        [
            'kodebarang' => $kodebarang
        ],
        [
            'namagabung' => $request->namagabung,
            'namabarang' => $namagabung,
            'kualitas' => $request->kualitas,
            'brand' => $request->brand,
            'kodejenis' => $request->kodejenis,
            'seri' => $request->seri,
            'satuan_b' => $request->satuan_b,
            'satuan_k' => $request->satuan_k,
            'isi' => $request->isi,
            'kategori' => $request->kategori,
            'hargajual1' => $request->hargajual1,
            'hargajual2' => $request->hargajual2,
            'hargabeli' => $request->hargabeli,
            'minim_stok' => $request->minim_stok,
            'ukuran' => $request->ukuran,
        ]);
        if ($request->has('rincians')) {
        $hasThumbnail = false; // Flag untuk menandai apakah sudah ada thumbnail

        foreach ($request->rincians as $img) {
                if (isset($img['gambar']) && $img['gambar']->isValid()) {
                    $path = $img['gambar']->store('images', 'public');

                    // Jika flag_thumbnail = 1 dan belum ada thumbnail sebelumnya
                    if (isset($img['flag_thumbnail']) && $img['flag_thumbnail'] === '1' && !$hasThumbnail) {
                        $flagThumbnail = '1';
                        $hasThumbnail = true; // Set flag bahwa sudah ada thumbnail
                    } else {
                        $flagThumbnail = null; // Reset flag_thumbnail untuk gambar lain
                    }

                    // Simpan gambar dengan flag_thumbnail
                    $simpan->rincians()->create([
                        'kodebarang' => $simpan->kodebarang,
                        'gambar' => $path,
                        'flag_thumbnail' => $flagThumbnail,
                    ]);
                }
            }
        }

        return new JsonResponse(
                [
                    'message' => 'Data Berhasil disimpan...!!!',
                    'result' => $simpan->load('rincians')
                ], 200);

    }
    public function setThumbnail(Request $request)
    {
        // Cari gambar yang dipilih berdasarkan ID
        $img = Imagebarang::find($request->id);

        if (!$img) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 500);
        }

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            // Ubah flag_thumbnail ke 0 untuk semua gambar terkait barang ini
            Imagebarang::where('kodebarang', $img->kodebarang)
                ->where('flag_thumbnail', '1')
                ->update(['flag_thumbnail' => NULL]);

            // Ubah flag_thumbnail ke 1 untuk gambar yang dipilih
            $img->flag_thumbnail = '1';
            $img->save();

            // Commit transaksi
            DB::commit();

            return new JsonResponse(['message' => 'Berhasil Memilih Thumbnail'], 200);
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return new JsonResponse(['message' => 'Gagal Memilih Thumbnail', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteItem(Request $request)
    {
        // nyoba biar bisa push

        $header = Barang::find($request->id);
        if (!$header) {
            return new JsonResponse(['message' => 'Data Tidak Ditemukan'], 500);
        }

        $header->delete();
        foreach ($header->rincians as $image) {
            $filePath = public_path('storage/' . $image->gambar);  // Ganti dengan path yang benar

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $header->rincians()->delete();

        return new JsonResponse(['message' => 'Data Sudah Dihapus'], 200);
    }

    public function deletegambar(Request $request)
    {
        // Cari gambar berdasarkan ID
        $image = Imagebarang::find($request->id);

        if (!$image) {
            return response()->json([
                'message' => 'Gambar tidak ditemukan',
            ], 404);
        }

        // Hapus file gambar dari storage (opsional)
        $filePath = public_path('storage/' . $image->gambar);  // Ganti dengan path yang benar

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Hapus data dari database
        $image->delete();

        return response()->json([
            'message' => 'Gambar berhasil dihapus',
        ], 200);
    }
}
