<?php

namespace App\Models;

use App\Models\Stok\stok;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    public function incrementViewCount()
    {
        $this->view_count = $this->view_count + 1;
        $this->save();
    }
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
    public function images()
    {
        return $this->hasMany(Imagebarang::class, 'kodebarang', 'kodebarang');
    }

    public function scopeMostViewed($query, $limit = 10)
    {
        return $query->orderBy('view_count', 'desc')->limit($limit);
    }
}
