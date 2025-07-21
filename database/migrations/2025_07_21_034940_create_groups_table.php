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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id'); // user_id from users table
            $table->string('name');
            $table->text('description')->nullable(); // Markdown
            $table->string('avatar_url')->nullable();
            $table->string('cover_url')->nullable();
            $table->timestamps();
        
            $table->foreign('owner_id')->references('user_id')->on('users')->onDelete('cascade');
        });
        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
