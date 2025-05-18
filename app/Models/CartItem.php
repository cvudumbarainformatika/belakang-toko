<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'barang_id', 'quantity', 'price'];

    public function product()
    {
        return $this->hasOne(Barang::class, 'id', 'barang_id');
    }
}
