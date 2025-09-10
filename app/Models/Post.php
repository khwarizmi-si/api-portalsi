<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';
    protected $primaryKey = 'post_id';

    protected $fillable = [
        'user_id',
        'caption',
        'media_url',
        'location',
        'is_archived',
        'is_video',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    public function likes()
    {
        return $this->hasMany(Like::class, 'post_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }

    public function mentions()
    {
        return $this->hasMany(PostMention::class, 'post_id');
    }

    public function bookmarks()
    {
    return $this->hasMany(Bookmark::class);
    }

    public function bookmarkedByUsers()
    {
    return $this->belongsToMany(User::class, 'bookmarks');
    }

}
