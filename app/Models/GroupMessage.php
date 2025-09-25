<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'sender_id',
        'content',
        'media_url',
        'sent_at',
        'is_edited',
        'is_deleted',
        'is_pinned',
        'reply_to', // ✅ tambahin ini
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'is_edited'  => 'boolean',
        'is_deleted' => 'boolean',
        'is_pinned'  => 'boolean',
        'reply_to'   => 'integer', // ✅ biar nggak dianggap array/string
    ];

    // Relasi ke pengirim pesan
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function mentions()
    {
        return $this->hasMany(GroupMessageMention::class);
    }

    public function replyTo()
    {
        return $this->belongsTo(GroupMessage::class, 'reply_to');
    }

    public function replies()
    {
        return $this->hasMany(GroupMessage::class, 'reply_to');
    }

    public function reads()
    {
        return $this->hasMany(GroupMessageRead::class, 'group_message_id');
    }
}
