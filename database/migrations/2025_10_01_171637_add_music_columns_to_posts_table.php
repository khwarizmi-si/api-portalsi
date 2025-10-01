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
        Schema::table('posts', function (Blueprint $table) {
            $table->string('music_track_name', 255)->nullable()->after('is_video');
            $table->string('music_artist_name', 255)->nullable()->after('music_track_name');
            $table->text('music_preview_url')->nullable()->after('music_artist_name');
            $table->string('music_album_art_url', 255)->nullable()->after('music_preview_url');
            $table->integer('music_start_position_ms')->nullable()->after('music_album_art_url');
            $table->string('music_clip_duration_ms', 255)->nullable()->after('music_start_position_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'music_track_name',
                'music_artist_name',
                'music_preview_url',
                'music_album_art_url',
                'music_start_position_ms',
                'music_clip_duration_ms',
            ]);
        });
    }
};
