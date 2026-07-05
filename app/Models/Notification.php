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
        'related_comment_id',
        'related_story_id',
        'comment_id',
        'reply_id',
        'created_at',
        'is_read',
    ];

    /**
     * Casting kolom otomatis.
     */
    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Buat notifikasi HANYA bila penerima mengizinkan tipe tsb (preferensi in-app).
     * Mengembalikan model, atau null bila ditekan oleh preferensi / penerima tak ada.
     * Pemanggil harus cek null sebelum broadcast.
     */
    public static function createFor(int $recipientId, array $attributes): ?self
    {
        $recipient = User::find($recipientId);
        if (! $recipient) {
            return null;
        }
        if (! $recipient->wantsNotificationType($attributes['type'] ?? null, $attributes)) {
            return null;
        }
        $attributes['recipient_id'] = $recipientId;
        if (! array_key_exists('created_at', $attributes)) {
            $attributes['created_at'] = now();
        }

        return static::create($attributes);
    }

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id', 'user_id');
    }

    /**
     * Post yang terkait dengan notifikasi.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    /**
     * Komentar yang terkait dengan notifikasi.
     */
    public function comment(): BelongsTo
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
