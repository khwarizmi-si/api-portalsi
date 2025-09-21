<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMessageRead extends Model
{
    protected $fillable = ['group_message_id', 'user_id', 'read_at'];

    public $timestamps = false;

    public function message()
    {
        return $this->belongsTo(GroupMessage::class, 'group_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
