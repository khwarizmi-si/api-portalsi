<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom color_pallete ke tabel stories
     */
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Kolom untuk menyimpan JSON string warna (misalnya: '["#007BFF", "#8BC34A"]')
            $table->string('color_pallete')->nullable()->after('music_sticker_position_y');
        });
    }

    /**
     * Rollback perubahan
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('color_pallete');
        });
    }
};
