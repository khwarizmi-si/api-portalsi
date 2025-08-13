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
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('related_comment_id')->nullable()->after('related_post_id');

            // Jika kamu punya tabel comments dan ingin relasi langsung
            $table->foreign('related_comment_id')
                  ->references('comment_id')
                  ->on('comments')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['related_comment_id']);
            $table->dropColumn('related_comment_id');
        });
    }
};
