<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->text('music_album_art_url')->nullable()->after('music_preview_url');
            $table->integer('music_start_position_ms')->nullable()->after('music_album_art_url');
            $table->integer('music_clip_duration_ms')->nullable()->after('music_start_position_ms');
            $table->string('music_display_style', 50)->nullable()->after('music_clip_duration_ms');

            // pakai double supaya bisa menampung angka desimal besar
            $table->double('music_sticker_position_x', 16, 10)->nullable()->after('music_display_style');
            $table->double('music_sticker_position_y', 16, 10)->nullable()->after('music_sticker_position_x');
        });
    }

    public function down()
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn([
                'music_album_art_url',
                'music_start_position_ms',
                'music_clip_duration_ms',
                'music_display_style',
                'music_sticker_position_x',
                'music_sticker_position_y',
            ]);
        });
    }
};
