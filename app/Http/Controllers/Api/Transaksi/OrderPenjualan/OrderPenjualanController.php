<?php

namespace App\Http\Controllers\Api\Transaksi\OrderPenjualan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\OrderPenjualan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderPenjualanController extends Controller
{
	public function index()
	{
		$query = OrderPenjualan::query();

		$data = $this->eagerLoadOrder($query)
			->orderBy('tglorder', 'DESC')
        	->simplePaginate(request('per_page'));

        return new JsonResponse($data);
	}

	protected function eagerLoadOrder($query)
	{
		return $query->select(
			'id', 'noorder', 'tglorder', 'pelanggan_id', 'sales_id', 
			'total_harga', 'status_order', 'status_pembayaran', 
			'tanggal_kirim', 'tanggal_terima','metode_bayar',
			'bayar','tempo','catatan'
		)->with([
			'rincians:order_penjualan_id,barang_id,jumlah,harga,satuan,satuans,subtotal', 
			'rincians.barang:id,namabarang,isi,satuan_k',
			'rincians.barang.images',  
			'pelanggan:id,nama,alamat,telepon,norek,namabank', 
			'sales:id,nama'
		]);

	}
}



