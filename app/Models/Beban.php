<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beban extends Model
{
    use HasFactory;
    protected $table = 'bebans';
    protected $guarded = ['id'];
}
