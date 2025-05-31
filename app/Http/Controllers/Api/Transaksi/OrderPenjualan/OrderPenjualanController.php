<?php

namespace App\Http\Controllers\Api\Transaksi\OrderPenjualan;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\OrderPenjualan;
use App\Models\OrderPenjualanRincian;
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
			'rincians:id,order_penjualan_id,barang_id,jumlah,harga,satuan,satuans,subtotal', 
			'rincians.barang:id,namabarang,isi,satuan_k,satuan_b',
			'rincians.barang.images',  
			'pelanggan:id,nama,alamat,telepon,norek,namabank', 
			'sales:id,nama'
		]);

	}


	public function updateRincian(Request $request)
	{
	   $rincian_id = $request->id;
	   $order_id = $request->order_id;

	   
	   $rincian = OrderPenjualanRincian::find($rincian_id);

	   $rincian->jumlah = $request->jumlah;
	   $rincian->satuan = $request->satuan;
	   $rincian->subtotal = $request->subtotal;

	   $rincian->save();

	   $order = OrderPenjualan::find($order_id);
	   $order->total_harga = $request->total_harga_order;
	   $order->save();

	   $data=['message'=> 'success','order'=>$order, 'rincian'=> $rincian];

	   return new JsonResponse($data);


	}
	public function deleteRincian(Request $request)
	{
	   $rincian_id = $request->id;
	   $order_id = $request->order_id;
	   
	   
	   $rincian = OrderPenjualanRincian::find($rincian_id);

	   $rincian->delete();

	   $order = OrderPenjualan::find($order_id);
	   $order->total_harga = $request->total_harga_order;
	   $order->save();

	   
	   $data=['message'=> 'success','order'=>$order, 'rincian'=> $rincian];

	   return new JsonResponse($data);


	}
	public function updateStatus(Request $request)
	{
	   $order_id = $request->order_id;

	   $order = OrderPenjualan::find($order_id);
	   $order->status_order = $request->status_order;
	   $order->save();

	   
	   $data=['message'=> 'success','order'=>$order];

	   return new JsonResponse($data);


	}
}



