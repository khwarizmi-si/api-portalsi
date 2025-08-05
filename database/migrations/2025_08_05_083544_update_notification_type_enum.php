<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE notifications MODIFY type ENUM(
            'like',
            'comment',
            'follow',
            'mention',
            'reply',
            'follow_accepted'
        ) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE notifications MODIFY type ENUM(
            'like',
            'comment',
            'follow',
            'mention',
            'reply'
        ) NOT NULL");
    }
};
