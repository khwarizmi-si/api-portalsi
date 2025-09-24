<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id('like_id');
            
            $table->unsignedBigInteger('comment_id');
            $table->unsignedBigInteger('user_id'); // FK ke users.user_id
            
            $table->timestamps();

            $table->foreign('comment_id')->references('comment_id')->on('comments')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->unique(['comment_id', 'user_id']); // supaya user tidak bisa like comment lebih dari sekali
        });
    }

    public function down()
    {
        Schema::dropIfExists('comment_likes');
    }
};
