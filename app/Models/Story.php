<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use HasFactory;

    protected $primaryKey = 'story_id';

    public $timestamps = false; // ⛔ Nonaktifkan updated_at & created_at otomatis

    protected $fillable = [
        'user_id',
        'media_url',
        'caption',
        'type',
        'music_track_name',
        'music_artist_name',
        'music_preview_url',
        'music_album_art_url',
        'music_start_position_ms',
        'music_clip_duration_ms',
        'music_display_style',
        'music_sticker_position_x',
        'music_sticker_position_y',
        'color_pallete', // ✅ tambahkan di fillable
        'created_at',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'music_start_position_ms' => 'integer',
        'music_clip_duration_ms' => 'integer',
        'music_sticker_position_x' => 'float',
        'music_sticker_position_y' => 'float',

        // ✅ otomatis decode/encode JSON string jadi array
        'color_pallete' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function mentions()
    {
        return $this->hasMany(StoryMention::class, 'story_id');
    }
}
