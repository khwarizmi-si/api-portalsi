<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Ubah jadi TEXT, tidak ada batas panjang seperti VARCHAR(255)
            $table->text('music_sticker_position_x')->nullable()->change();
            $table->text('music_sticker_position_y')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Kembalikan ke DOUBLE (atau sesuai tipe awal)
            $table->double('music_sticker_position_x')->nullable()->change();
            $table->double('music_sticker_position_y')->nullable()->change();
        });
    }
};
