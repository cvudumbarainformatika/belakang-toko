<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarangLike extends Model
{
    use HasFactory;
    protected $table = 'product_likes';
    protected $fillable = ['barang_id', 'likes'];
}
