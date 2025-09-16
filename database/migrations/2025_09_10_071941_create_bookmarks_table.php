<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('post_id');

            // Timestamps (created_at & updated_at)
            $table->timestamps();

            // Constraint: user tidak bisa bookmark post yang sama lebih dari sekali
            $table->unique(['user_id', 'post_id']);

            // Foreign key ke tabel users.user_id
            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');

            // Foreign key ke tabel posts.post_id
            $table->foreign('post_id')
                  ->references('post_id')
                  ->on('posts')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Drop constraints dulu biar aman rollback
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['post_id']);
        });

        Schema::dropIfExists('bookmarks');
    }
};
