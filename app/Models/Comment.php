<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $primaryKey = 'comment_id';

    protected $fillable = [
        'post_id',
        'user_id',
        'content',
        'gif_url',
        'parent_comment_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_comment_id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id');
    }

    // App\Models\Comment.php
public function likes()
{
    return $this->hasMany(\App\Models\CommentLike::class, 'comment_id', 'comment_id');
}

protected $appends = ['is_liked'];

public function getIsLikedAttribute()
{
    $authId = auth()->id();
    if (!$authId) return false;

    return $this->likes()->where('user_id', $authId)->exists();
}

}
