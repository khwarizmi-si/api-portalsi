<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();              // Judul opsional
            $table->text('content')->nullable();              // Isi teks
            $table->string('image_url')->nullable();          // Gambar opsional
            $table->json('poll_data')->nullable();            // Polling (opsional, dalam bentuk JSON)
            $table->boolean('pinned')->default(false);        // Apakah dipin?
            $table->foreignId('created_by')->constrained('users', 'user_id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
