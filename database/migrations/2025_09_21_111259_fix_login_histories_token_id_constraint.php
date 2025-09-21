<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cek apakah foreign key exists sebelum mencoba drop
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = 'login_histories' 
            AND COLUMN_NAME = 'token_id' 
            AND CONSTRAINT_NAME != 'PRIMARY'
        ");

        // Jika ada foreign key, drop itu
        if (!empty($foreignKeys)) {
            Schema::table('login_histories', function (Blueprint $table) use ($foreignKeys) {
                foreach ($foreignKeys as $fk) {
                    $table->dropForeign([$fk->CONSTRAINT_NAME]);
                }
            });
        }

        // Ubah column menjadi nullable (tanpa constraint)
        Schema::table('login_histories', function (Blueprint $table) {
            // Untuk MySQL, gunakan raw statement untuk avoid error
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE login_histories MODIFY token_id BIGINT UNSIGNED NULL');
            } else {
                $table->unsignedBigInteger('token_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke not nullable (jika needed)
        Schema::table('login_histories', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE login_histories MODIFY token_id BIGINT UNSIGNED NOT NULL');
            } else {
                $table->unsignedBigInteger('token_id')->nullable(false)->change();
            }
        });

        // Tambahkan foreign key kembali (optional)
        Schema::table('login_histories', function (Blueprint $table) {
            $table->foreign('token_id')
                  ->references('id')
                  ->on('personal_access_tokens')
                  ->onDelete('set null');
        });
    }
};