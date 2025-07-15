<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostMention extends Model
{
    use HasFactory;

    protected $table = 'post_mentions';
    public $timestamps = false; // 🛑 Jangan gunakan created_at / updated_at

    protected $fillable = [
        'post_id',
        'mentioned_user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }
}
