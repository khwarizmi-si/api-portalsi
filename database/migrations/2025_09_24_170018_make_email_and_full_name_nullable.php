<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeEmailAndFullNameNullable extends Migration
{
    public function up()
    {
        // NOTE: Changing column nullability requires doctrine/dbal package for ->change()
        // composer require doctrine/dbal
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
}
