<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('music_sticker_position_x', 50)->nullable()->change();
            $table->string('music_sticker_position_y', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->double('music_sticker_position_x')->nullable()->change();
            $table->double('music_sticker_position_y')->nullable()->change();
        });
    }
};
