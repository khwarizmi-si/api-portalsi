<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('user_id');
            $table->foreign('created_by_user_id')->references('user_id')->on('users')->nullOnDelete();
        });

        DB::table('portfolios')->whereNull('created_by_user_id')->update([
            'created_by_user_id' => DB::raw('user_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn('created_by_user_id');
        });
    }
};
