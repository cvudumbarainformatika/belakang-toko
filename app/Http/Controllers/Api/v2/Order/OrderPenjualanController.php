<?php
namespace App\Http\Controllers\Api\v2\Order;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\OrderPenjualan; // pastikan model sudah dibuat
use Illuminate\Support\Facades\DB;

class OrderPenjualanController extends Controller
{
    public function generateNoOrder()
    {
        do {
            $date = now()->format('ymd');
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $noorder = "{$date}-{$random}-OPJ";
        } while (\App\Models\OrderPenjualan::where('noorder', $noorder)->exists());

        return $noorder;
    }

    public function orderPenjualan(Request $request)
    {
        // Generate noorder unik
        $noorder = $this->generateNoOrder();

        // Validasi input utama dan rincian
        $validated = $request->validate([
            // 'noorder' dihapus dari validasi request, karena di-generate otomatis
            'tglorder'           => 'required|date',
            'pelanggan_id'       => 'required|exists:users,id',
            'sales_id'           => 'required|exists:users,id',
            'total_harga'        => 'required|numeric|min:0',
            'status_order'       => 'nullable|in:1,2,3',
            'status_pembayaran'  => 'nullable|in:1,2',
            'rincians'           => 'required|array|min:1',
            'rincians.*.produk_id' => 'required|exists:products,id',
            'rincians.*.qty'       => 'required|integer|min:1',
            'rincians.*.harga'     => 'required|numeric|min:0',
            'rincians.*.subtotal'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Simpan order penjualan
            $order = OrderPenjualan::create([
                'noorder'           => $noorder,
                'tglorder'          => $validated['tglorder'],
                'pelanggan_id'      => $validated['pelanggan_id'],
                'sales_id'          => $validated['sales_id'],
                'total_harga'       => $validated['total_harga'],
                'status_order'      => $validated['status_order'] ?? '1',
                'status_pembayaran' => $validated['status_pembayaran'] ?? '1',
            ]);

            // Simpan rincian order penjualan
            foreach ($validated['rincians'] as $rincian) {
                $order->rincians()->create([
                    'produk_id' => $rincian['produk_id'],
                    'qty'       => $rincian['qty'],
                    'harga'     => $rincian['harga'],
                    'subtotal'  => $rincian['subtotal'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order penjualan & rinciannya berhasil dibuat.',
                'data'    => $order->load('rincians')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat order penjualan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
