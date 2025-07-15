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
        Schema::create('direct_messages', function (Blueprint $table) {
            $table->id('message_id');
            $table->foreignId('sender_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->text('content');
            $table->string('media_url')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->boolean('is_read')->default(false);
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_messages');
    }
};
