<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPenjualanRincian extends Model
{
    use HasFactory;

    protected $table = 'order_penjualan_rincians';

    protected $guarded = ['id'];

    // Relasi ke order penjualan
    public function orderPenjualan()
    {
        return $this->belongsTo(OrderPenjualan::class, 'order_penjualan_id');
    }

    // Relasi ke produk (jika ada model produk)
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}