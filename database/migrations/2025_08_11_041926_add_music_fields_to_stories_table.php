<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('type', 50)->default('image')->after('media_url'); // image, video, music
            $table->string('music_track_name', 255)->nullable()->after('type');
            $table->string('music_artist_name', 255)->nullable()->after('music_track_name');
            $table->text('music_preview_url')->nullable()->after('music_artist_name');
            $table->integer('music_start_position_ms')->nullable()->after('music_preview_url');
            $table->string('music_display_style', 50)->nullable()->after('music_start_position_ms');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'music_track_name',
                'music_artist_name',
                'music_preview_url',
                'music_start_position_ms',
                'music_display_style'
            ]);
        });
    }
};
