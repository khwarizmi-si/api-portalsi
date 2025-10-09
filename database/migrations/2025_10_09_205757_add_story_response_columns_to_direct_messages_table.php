<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            // Tambah kolom untuk menandai apakah pesan ini adalah respons terhadap story
            $table->boolean('is_story_response')->default(false)->after('content');

            // Relasi opsional ke tabel stories (jika ada)
            $table->unsignedBigInteger('story_id')->nullable()->after('is_story_response');

            // URL media story yang direspons
            $table->string('responded_media_url')->nullable()->after('story_id');

            // (Opsional) jika kamu punya tabel stories dan ingin buat foreign key:
            // $table->foreign('story_id')->references('id')->on('stories')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            // Hapus foreign key jika ada
            // $table->dropForeign(['story_id']);

            $table->dropColumn(['is_story_response', 'story_id', 'responded_media_url']);
        });
    }
};
