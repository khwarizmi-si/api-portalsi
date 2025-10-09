<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectMessage extends Model
{
    use HasFactory;

    protected $table = 'direct_messages';
    protected $primaryKey = 'message_id';
    public $timestamps = false;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'content',
        'media_url',
        'is_read',
        'sent_at',

        // ⬇️ Tambahan baru untuk reply story
        'is_story_response',
        'story_id',
        'responded_media_url',
    ];

    protected $casts = [
        'is_read'           => 'boolean',
        'is_story_response' => 'boolean',
        'sent_at'           => 'datetime',
    ];

    // (opsional) relasi ke pengirim & penerima
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id', 'user_id');
    }

    // (opsional) relasi ke story
    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id', 'story_id');
    }
}
