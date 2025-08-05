<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// ⬇️ Tambahan untuk verifikasi email
use Illuminate\Contracts\Auth\MustVerifyEmail;

// ⬇️ Relasi
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Story;
use App\Models\DirectMessage;
use App\Models\Notification;
use App\Notifications\CustomVerifyEmail;
use App\Notifications\CustomResetPassword;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    // 🟢 PRIMARY KEY custom: user_id
    protected $primaryKey = 'user_id';

    /**
     * Mass assignment
     */
    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password_hash',
        'bio',
        'profile_picture_url',
        'is_verified',       // centang biru
        'is_private',
        'role',              // dev, teacher, parent, student
        'email_verified_at', // verifikasi email
    ];

    /**
     * Hidden dari JSON response
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Casting otomatis
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_private' => 'boolean',
    ];

    /**
     * Agar Laravel pakai "password_hash" untuk login
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

// User.php
public function followers()
{
    return $this->belongsToMany(User::class, 'follows', 'followed_id', 'follower_id')
        ->withPivot('followed_at', 'status'); // tanpa withTimestamps
}

public function following()
{
    return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id')
        ->withPivot('followed_at', 'status'); // tanpa withTimestamps
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

    public function sendEmailVerificationNotification()
    {
    $this->notify(new CustomVerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
    $this->notify(new CustomResetPassword($token));
    }
    public function ownedGroups()
    {
    return $this->hasMany(Group::class, 'owner_id', 'user_id');
    }

    public function groupMemberships()
    {
    return $this->hasMany(GroupMember::class, 'user_id', 'user_id');
    }

    public function groupMessages()
    {
    return $this->hasMany(GroupMessage::class, 'sender_id', 'user_id');
    }
}
