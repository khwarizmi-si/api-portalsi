<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public $timestamps = false; // Karena kita isi created_at manual
    protected $primaryKey = 'notification_id'; // ✅ Ini WAJIB karena kamu pakai nama custom
    protected $keyType = 'int'; // default integer (tidak perlu ubah jika auto increment)

    protected $fillable = [
        'recipient_id',
        'type',
        'related_user_id',
        'related_post_id',
        'created_at',
        'is_read'
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'created_at'  => 'datetime', // ✅ agar bisa pakai diffInSeconds() dan fungsi date lainnya
    ];

    // RELATIONSHIPS
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_id');
    }

    public function reply()
    {
        return $this->belongsTo(Comment::class, 'reply_id', 'comment_id');
    }
}
