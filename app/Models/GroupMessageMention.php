<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupMessageMention extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'group_message_id', 'mentioned_user_id',
    ];

    public function message()
    {
        return $this->belongsTo(GroupMessage::class);
    }

    public function mentioned()
    {
        return $this->belongsTo(User::class, 'mentioned_user_id', 'user_id');
    }


}

