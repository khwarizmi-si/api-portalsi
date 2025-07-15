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
        Schema::create('story_views', function (Blueprint $table) {
            $table->foreignId('story_id')->constrained('stories', 'story_id')->onDelete('cascade');
            $table->foreignId('viewer_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->timestamp('viewed_at')->useCurrent();
            $table->primary(['story_id', 'viewer_id']);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_views');
    }
};
