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
            $table->string('music_album_art_url', 255)->nullable()->change();
            $table->string('music_clip_duration_ms', 255)->nullable()->change();
            $table->string('music_sticker_position_x', 255)->nullable()->change();
            $table->string('music_sticker_position_y', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            // Balikkan ke tipe sebelumnya (sesuai migration awal)
            $table->text('music_album_art_url')->nullable()->change();
            $table->integer('music_clip_duration_ms')->nullable()->change();
            $table->decimal('music_sticker_position_x', 10, 8)->nullable()->change();
            $table->decimal('music_sticker_position_y', 10, 8)->nullable()->change();
        });
    }
};
