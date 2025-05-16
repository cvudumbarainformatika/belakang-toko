<?php
namespace App\Http\Controllers\Api\v2\Product;


use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;



// app/Http/Controllers/CartController.php

class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'items' => $items->map(function ($item) {
                return [
                    'barang_id' => $item->barang_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'name' => $item->product->name,
                    'image' => $item->product->image,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'required|numeric'
        ]);

        $user = Auth::user();

        $item = CartItem::updateOrCreate(
            ['user_id' => $user->id, 'barang_id' => $request->barang_id],
            ['quantity' => $request->quantity, 'price' => $request->price]
        );

        return response()->json(['success' => true, 'item' => $item]);
    }

    public function update(Request $request, Barang $barang)
    {
        $request->validate(['quantity' => 'required|integer|min:1']);

        $item = CartItem::where('user_id', $request->user()->id)
            ->where('barang_id', $barang->id)
            ->firstOrFail();

        $item->update(['quantity' => $request->quantity]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Barang $barang)
    {
        CartItem::where('user_id', $request->user()->id)
            ->where('barang_id', $barang->id)
            ->delete();

        return response()->json(['success' => true]);
    }
}
