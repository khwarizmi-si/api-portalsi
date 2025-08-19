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
        Schema::table('stories', function (Blueprint $table) {
            // Ubah kolom ke DOUBLE agar bisa menampung angka besar + pecahan
            $table->double('music_sticker_position_x')->nullable()->change();
            $table->double('music_sticker_position_y')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Kembalikan ke decimal (ubah sesuai tipe awal Anda kalau bukan DECIMAL(8,2))
            $table->decimal('music_sticker_position_x', 8, 2)->nullable()->change();
            $table->decimal('music_sticker_position_y', 8, 2)->nullable()->change();
        });
    }
};
