<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->dateTime('sent_at')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('group_messages', function (Blueprint $table) {
            $table->timestamp('sent_at')->default(DB::raw('CURRENT_TIMESTAMP'))->change();
        });
    }
};

