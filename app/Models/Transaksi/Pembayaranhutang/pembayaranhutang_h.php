<?php

namespace App\Models\Transaksi\Pembayaranhutang;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class pembayaranhutang_h extends Model
{
    use HasFactory;
    protected $table = 'pembayaran_hutang_h';
    protected $guarded = ['id'];
}
