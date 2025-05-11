<?php

namespace App\Models;

use App\Models\Stok\Penyesuaian;
use App\Models\Stok\stok;
use App\Models\Transaksi\Penerimaan\Penerimaan_r;
use App\Models\Transaksi\Penjualan\DetailPenjualanFifo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function rincians()
    {
        return $this->hasMany(Imagebarang::class, 'kodebarang', 'kodebarang');
    }
    public function stoks()
    {
        return $this->hasMany(stok::class, 'kdbarang', 'kodebarang');
    }
    public function stok()
    {
        return $this->hasOne(stok::class, 'kdbarang', 'kodebarang');
    }

    public function penerimaan()
    {
        return $this->hasMany(Penerimaan_r::class, 'kdbarang', 'kodebarang');
    }
    public function penjualan()
    {
        return $this->hasMany(DetailPenjualanFifo::class, 'kodebarang', 'kodebarang');
    }
     public function penyesuaian()
    {
        return $this->hasMany(Penyesuaian::class, 'kdbarang', 'kodebarang');
    }
}
