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
        Schema::create('group_message_mentions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_message_id');
            $table->unsignedBigInteger('mentioned_user_id');
        
            $table->foreign('group_message_id')->references('id')->on('group_messages')->onDelete('cascade');
            $table->foreign('mentioned_user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_messages');
    }
};
