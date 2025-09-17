<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * Karena kolom created_at diisi manual.
     */
    public $timestamps = false;

    /**
     * Primary key custom.
     */
    protected $primaryKey = 'notification_id';
    protected $keyType    = 'int';

    /**
     * Kolom yang bisa diisi mass-assignment.
     */
    protected $fillable = [
        'recipient_id',
        'type',
        'related_user_id',
        'related_post_id',
        'related_comment_id',
        'comment_id',
        'reply_id',
        'created_at',
        'is_read',
    ];

    /**
     * Casting kolom otomatis.
     */
    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Penerima notifikasi (user yang menerima notifikasi).
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id', 'user_id');
    }

    /**
     * Pengirim notifikasi (user yang melakukan aksi).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id', 'user_id');
    }

    /**
     * Alias untuk sender, agar kode lama $notification->relatedUser tetap jalan.
     */
    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id', 'user_id');
    }

    /**
     * Post yang terkait dengan notifikasi.
     */
    public function relatedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    /**
     * Komentar yang terkait dengan notifikasi.
     */
    public function relatedComment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_id');
    }

    /**
     * Balasan komentar yang terkait.
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'reply_id', 'comment_id');
    }
}
