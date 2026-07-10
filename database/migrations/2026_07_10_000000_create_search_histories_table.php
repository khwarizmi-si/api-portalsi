<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('type', 20)->default('keyword');
            $table->string('query', 120);
            $table->string('query_key', 140)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('target_user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['user_id', 'deleted_at', 'updated_at']);
            $table->index(['user_id', 'type', 'query_key']);
            $table->index(['user_id', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
