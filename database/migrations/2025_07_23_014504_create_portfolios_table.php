<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Pemilik karya
            $table->string('aspect'); // Quran / IT / Bahasa / Karakter
            $table->string('title'); // Judul karya / prestasi
            $table->text('description')->nullable(); // Deskripsi karya
            $table->string('media_url')->nullable(); // Link ke foto/pdf (disimpan di public)
            $table->year('year')->nullable(); // Tahun pembuatan/angkatan
            $table->timestamps();

            // Foreign key
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
