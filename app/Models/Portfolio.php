<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'aspect',
        'title',
        'description',
        'media_url',
        'year',
    ];

    // Relasi ke pemilik karya
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
