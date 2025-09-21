<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
// database/migrations/xxxx_xx_xx_fix_login_histories_token_id_constraint.php
public function up()
{
    Schema::table('login_histories', function (Blueprint $table) {
        // Hapus foreign key constraint jika ada
        $table->dropForeign(['token_id']);
        
        // Ubah menjadi regular unsignedBigInteger tanpa constraint
        $table->unsignedBigInteger('token_id')->nullable()->change();
    });
}

public function down()
{
    Schema::table('login_histories', function (Blueprint $table) {
        // Kembalikan foreign key (optional)
        $table->foreign('token_id')
              ->references('id')
              ->on('personal_access_tokens')
              ->onDelete('set null');
    });
}
};
