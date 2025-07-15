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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('recipient_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->enum('type', ['like', 'comment', 'follow', 'mention', 'reply']);
            $table->foreignId('related_user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('related_post_id')->nullable()->constrained('posts', 'post_id')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('is_read')->default(false);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
