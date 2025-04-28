<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detail_retur_penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('no_retur');
            $table->string('no_penjualan');
            $table->unsignedBigInteger('detail_penjualan_id')->nullable();
            $table->unsignedBigInteger('stok_id')->nullable();
            $table->dateTime('tgl')->nullable();
            $table->double('jumlah', 24, 2)->default(0);
            $table->double('harga_jual', 24, 2)->default(0);
            $table->double('subtotal', 24, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_retur_penjualans');
    }
};
