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
    protected $keyType = 'int';

    /**
     * Kolom yang bisa diisi mass-assignment.
     */
    protected $fillable = [
        'recipient_id',
        'type',
        'related_user_id',
        'related_post_id',
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
        'created_at' => 'datetime', // bisa pakai ->diffInSeconds(), ->format(), dll.
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Penerima notifikasi (user yang dapat notifikasi).
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Pengirim notifikasi (user yang melakukan aksi).
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    /**
     * Alias untuk sender, biar kode lama `$notification->relatedUser` tetap jalan.
     */
    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    /**
     * Post yang terkait.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    /**
     * Komentar yang terkait.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'comment_id');
    }

    /**
     * Reply yang terkait (balasan komentar).
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'reply_id', 'comment_id');
    }
}
