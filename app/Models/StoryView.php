<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'story_id',
        'viewer_id',
        'viewed_at',
    ];

    // Relasi opsional (kalau ingin menampilkan siapa yang melihat)
    public function viewer()
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id');
    }
}
