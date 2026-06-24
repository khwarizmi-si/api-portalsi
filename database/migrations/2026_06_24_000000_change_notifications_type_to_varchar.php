<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Convert notifications.type from a restrictive ENUM to VARCHAR(50).
     *
     * The ENUM kept truncating whenever a new notification type was introduced
     * in code without a matching ALTER (e.g. 'new_post', 'group', 'dm',
     * 'suggestion'), producing "SQLSTATE[01000] Data truncated for column
     * 'type'" 500 errors during post creation. VARCHAR avoids the recurring
     * enum whack-a-mole while keeping every existing value valid.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM(
            'like',
            'comment',
            'follow',
            'mention',
            'reply',
            'follow_accepted',
            'story_mention'
        ) NOT NULL");
    }
};
