<?php

namespace App\Models\Transaksi\Penjualan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeaderReturPenjualan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function detail()
    {
        return $this->hasMany(DetailReturPenjualan::class);
    }
}
