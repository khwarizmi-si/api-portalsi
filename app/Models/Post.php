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
        'thumbnail_url',
        'location',
        'is_archived',
        'is_video',
        'music_track_name',
        'music_artist_name',
        'music_preview_url',
        'music_album_art_url',
        'music_start_position_ms',
        'music_clip_duration_ms',
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
    return $this->hasMany(Bookmark::class, 'post_id');
}


public function bookmarkedByUsers()
{
    return $this->belongsToMany(User::class, 'bookmarks', 'post_id', 'user_id')
                ->withTimestamps();
}


}
