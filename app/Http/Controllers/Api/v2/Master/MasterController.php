<?php
namespace App\Http\Controllers\Api\v2\Master;


use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;



// app/Http/Controllers/CartController.php

class MasterController extends Controller
{
    public function sales()
    {
        $query = User::query();

        $query->select('id', 'nama', 'email', 'nohp', 'alamat','avatar','username','jabatan')
            ->where('kodejabatan', 3)
            ->orderBy('nama', 'ASC');
        $sales = $query->get();
        return response()->json($sales);
    }
    public function pelanggan()
    {
        $query = User::query();

        $query->select('id', 'nama', 'email', 'nohp', 'alamat','avatar','username','jabatan')
            ->when(request('q'), function ($query) {
                $query->where('nama', 'like', '%' . request('q') . '%');
            })
            ->whereNull('kodejabatan')
            ->orderBy('nama', 'ASC');
        $sales = $query->limit(20)->get();
        return response()->json($sales);
    }
}
