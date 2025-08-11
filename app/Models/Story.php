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
        'music_start_position_ms',
        'music_display_style',
        'created_at',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
