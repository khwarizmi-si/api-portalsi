<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// ⬇️ IMPORT model lain jika diperlukan
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Story;
use App\Models\DirectMessage;
use App\Models\Notification;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // 🟢 PRIMARY KEY custom: user_id (WAJIB!)
    protected $primaryKey = 'user_id';

    /**
     * Field yang bisa diisi secara massal (mass assignable)
     */
    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password_hash',
        'bio',
        'profile_picture_url',
        'is_verified',
        'is_private',
    ];

    /**
     * Field yang disembunyikan saat return JSON
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Field yang di-cast otomatis
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Agar Laravel tahu bahwa password kamu pakai "password_hash" bukan "password"
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /* ================================
     | 💡 RELASI ELOQUENT
     ================================= */

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }

    public function likes()
    {
        return $this->hasMany(Like::class, 'user_id');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
            ->withPivot('followed_at', 'status'); // ❌ HAPUS withTimestamps()
    }
    
    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
            ->withPivot('followed_at', 'status'); // ❌ HAPUS withTimestamps()
    }
    

    public function stories()
    {
        return $this->hasMany(Story::class, 'user_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(DirectMessage::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(DirectMessage::class, 'receiver_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }
}
