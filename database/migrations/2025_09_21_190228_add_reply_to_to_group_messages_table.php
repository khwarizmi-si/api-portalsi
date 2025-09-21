<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to')->nullable()->after('sender_id');

            $table->foreign('reply_to')
                ->references('id')->on('group_messages')
                ->nullOnDelete(); // kalau pesan asal dihapus → reply tetap ada tapi nilainya null
        });
    }

    public function down(): void
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to']);
            $table->dropColumn('reply_to');
        });
    }
};
