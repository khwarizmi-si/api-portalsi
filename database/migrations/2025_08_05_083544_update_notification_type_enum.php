use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateNotificationTypeEnum extends Migration
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
}
