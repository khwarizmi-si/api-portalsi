<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StoryMention extends Model
{
    use HasFactory;

    protected $primaryKey = 'mention_id';

    protected $fillable = [
        'story_id',
        'mentioned_user_id',
    ];

    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id');
    }

    public function mentionedUser()
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}

