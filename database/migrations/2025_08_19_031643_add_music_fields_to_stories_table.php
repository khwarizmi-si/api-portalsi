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
            $table->text('music_album_art_url')->nullable()->after('music_preview_url');
            $table->integer('music_clip_duration_ms')->nullable()->after('music_start_position_ms');
            $table->decimal('music_sticker_position_x', 10, 8)->nullable()->after('music_display_style');
            $table->decimal('music_sticker_position_y', 10, 8)->nullable()->after('music_sticker_position_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn([
                'music_album_art_url',
                'music_clip_duration_ms',
                'music_sticker_position_x',
                'music_sticker_position_y'
            ]);
        });
    }
};
