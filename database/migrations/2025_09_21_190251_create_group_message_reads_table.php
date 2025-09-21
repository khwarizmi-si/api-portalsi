<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_message_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_message_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');

            $table->unique(['group_message_id', 'user_id']);

            $table->foreign('group_message_id')
                ->references('id')->on('group_messages')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('user_id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_message_reads');
    }
};
