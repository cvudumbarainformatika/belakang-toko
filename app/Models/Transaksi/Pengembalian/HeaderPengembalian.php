<?php

namespace App\Models\Transaksi\Pengembalian;

use App\Models\Transaksi\Penjualan\HeaderPenjualan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeaderPengembalian extends Model
{
    use HasFactory;
    protected $guarded = ['id'];



    protected $casts = [
        'tanggal' => 'datetime'
    ];

    // Relationships
    public function penjualan()
    {
        return $this->belongsTo(HeaderPenjualan::class, 'header_penjualan_id');
    }

    public function details()
    {
        return $this->hasMany(DetailPengembalian::class, 'header_pengembalian_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
