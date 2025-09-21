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
        Schema::table('login_histories', function (Blueprint $table) {
            // Hapus foreign key constraint terlebih dahulu
            $table->dropForeign(['user_id']);
            
            // Ubah column user_id menjadi nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Tambahkan foreign key constraint kembali dengan onDelete('set null')
            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_histories', function (Blueprint $table) {
            // Hapus foreign key constraint
            $table->dropForeign(['user_id']);
            
            // Kembalikan user_id menjadi not nullable
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            
            // Tambahkan foreign key constraint kembali dengan onDelete('cascade')
            $table->foreign('user_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
};