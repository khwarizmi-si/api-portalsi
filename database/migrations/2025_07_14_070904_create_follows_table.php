<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
// database/migrations/xxxx_xx_xx_create_follows_table.php
public function up()
{
    Schema::create('follows', function (Blueprint $table) {
        $table->id();
        $table->foreignId('follower_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('followed_id')->constrained('users')->onDelete('cascade');
        $table->timestamps();

        $table->unique(['follower_id', 'followed_id']); // tidak boleh follow dua kali
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
