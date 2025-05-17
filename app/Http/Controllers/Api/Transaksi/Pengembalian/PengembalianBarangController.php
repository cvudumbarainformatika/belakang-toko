<?php

namespace App\Http\Controllers\Api\Transaksi\Pengembalian;

use App\Http\Controllers\Controller;
use App\Models\Transaksi\Pengembalian\HeaderPengembalian;
use App\Models\Transaksi\Pengembalian\DetailPengembalian;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PengembalianBarangController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = HeaderPengembalian::with(['penjualan', 'details.barang', 'creator'])
                ->orderBy($request->sortBy ?? 'tanggal', $request->sortDesc ? 'desc' : 'asc');

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('no_pengembalian', 'like', "%{$request->search}%")
                        ->orWhereHas('penjualan', function ($q) use ($request) {
                            $q->where('no_penjualan', 'like', "%{$request->search}%");
                        });
                });
            }

            $pengembalians = $query->paginate($request->perPage ?? 10);

            return new JsonResponse([
                'message' => 'Data pengembalian barang berhasil diambil',
                'data' => $pengembalians
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => 'Error mengambil data pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'penjualan_id' => 'required|exists:penjualans,id',
            'keterangan' => 'required|string',
            'details' => 'required|array|min:1',
            'details.*.barang_id' => 'required|exists:barangs,id',
            'details.*.qty' => 'required|numeric|min:1',
            'details.*.keterangan_rusak' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            // Create header
            $header = HeaderPengembalian::create([
                'no_pengembalian' => 'RTN' . date('YmdHis'),
                'penjualan_id' => $request->penjualan_id,
                'tanggal' => Carbon::now(),
                'keterangan' => $request->keterangan,
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            // Create details dan update stok
            foreach ($request->details as $detail) {
                // Create detail pengembalian
                DetailPengembalian::create([
                    'header_pengembalian_id' => $header->id,
                    'barang_id' => $detail['barang_id'],
                    'qty' => $detail['qty'],
                    'keterangan_rusak' => $detail['keterangan_rusak'],
                    'status' => 'pending'
                ]);

                // Update qty retur di detail_penjualan_fifos
                $penjualanFifo = DetailPenjualanFifo::where('no_penjualan', $header->penjualan->no_penjualan)
                    ->where('barang_id', $detail['barang_id'])
                    ->first();

                if ($penjualanFifo) {
                    $penjualanFifo->retur += $detail['qty'];
                    $penjualanFifo->save();
                }
            }

            DB::commit();

            $data = $header->load(['penjualan', 'details.barang']);
            return new JsonResponse([
                'message' => 'Pengembalian barang berhasil disimpan',
                'data' => $data
            ], 201);
        } catch (Exception $e) {
            DB::rollback();
            return new JsonResponse([
                'message' => 'Error menyimpan pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $pengembalian = HeaderPengembalian::with([
                'penjualan',
                'details.barang',
                'creator'
            ])->findOrFail($id);

            return new JsonResponse([
                'message' => 'Detail pengembalian barang berhasil diambil',
                'data' => $pengembalian
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'message' => 'Error mengambil detail pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $header = HeaderPengembalian::with('details')->findOrFail($id);

            // Update header status
            $header->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => Carbon::now()
            ]);

            // Update detail status
            foreach ($header->details as $detail) {
                $detail->update(['status' => 'approved']);

                // Here you can add additional logic for stock updates if needed
            }

            DB::commit();

            return new JsonResponse([
                'message' => 'Pengembalian barang berhasil disetujui',
                'data' => $header->load(['penjualan', 'details.barang'])
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return new JsonResponse([
                'message' => 'Error menyetujui pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reject($id)
    {
        try {
            DB::beginTransaction();

            $header = HeaderPengembalian::with('details')->findOrFail($id);

            // Update header status
            $header->update([
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => Carbon::now()
            ]);

            // Update detail status
            foreach ($header->details as $detail) {
                $detail->update(['status' => 'rejected']);

                // Rollback quantity in FIFO record
                $penjualanFifo = DetailPenjualanFifo::where('no_penjualan', $header->penjualan->no_penjualan)
                    ->where('barang_id', $detail->barang_id)
                    ->first();

                if ($penjualanFifo) {
                    $penjualanFifo->retur -= $detail->qty;
                    $penjualanFifo->save();
                }
            }

            DB::commit();

            return new JsonResponse([
                'message' => 'Pengembalian barang berhasil ditolak',
                'data' => $header->load(['penjualan', 'details.barang'])
            ]);
        } catch (Exception $e) {
            DB::rollback();
            return new JsonResponse([
                'message' => 'Error menolak pengembalian barang: ' . $e->getMessage()
            ], 500);
        }
    }
}
