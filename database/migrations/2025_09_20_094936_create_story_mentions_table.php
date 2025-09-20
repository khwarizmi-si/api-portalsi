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
Schema::create('story_mentions', function (Blueprint $table) {
    $table->id('mention_id');
    $table->unsignedBigInteger('story_id');
    $table->unsignedBigInteger('mentioned_user_id');
    $table->timestamps();

    $table->foreign('story_id')->references('story_id')->on('stories')->onDelete('cascade');
    $table->foreign('mentioned_user_id')->references('user_id')->on('users')->onDelete('cascade');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_mentions');
    }
};
