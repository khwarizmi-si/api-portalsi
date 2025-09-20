<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        DB::statement("
            ALTER TABLE notifications 
            MODIFY COLUMN type ENUM('like','comment','follow','mention','reply','follow_accepted','story_mention')
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE notifications 
            MODIFY COLUMN type ENUM('like','comment','follow','mention','reply','follow_accepted')
        ");
    }
};
