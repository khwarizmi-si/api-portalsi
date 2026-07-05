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
        'banner_url',
        'is_verified',       // centang biru
        'is_private',
        'role',              // dev, teacher, parent, student
        'email_verified_at', // verifikasi email
        'is_online',         // online status
        'last_seen',         // last seen timestamp
        'last_activity',     // last activity timestamp
        'notification_preferences', // preferensi notifikasi in-app
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
        'is_online' => 'boolean',
        'last_seen' => 'datetime',
        'last_activity' => 'datetime',
        'notification_preferences' => 'array',
    ];

    /**
     * Default preferensi notifikasi in-app.
     *   new_post_reminders: all | mutual | off
     *   likes, comments, mentions, follows: bool
     */
    public static function defaultNotificationPreferences(): array
    {
        return [
            'new_post_reminders' => 'all',
            'likes' => true,
            'comments' => true,
            'mentions' => true,
            'follows' => true,
        ];
    }

    /** Preferensi lengkap (default digabung dengan yang tersimpan). */
    public function notificationPreferences(): array
    {
        return array_merge(self::defaultNotificationPreferences(), $this->notification_preferences ?? []);
    }

    /**
     * Apakah user ini ingin menerima notifikasi in-app untuk tipe tertentu.
     * $context boleh berisi 'related_user_id' (pelaku) untuk aturan "mutual".
     */
    public function wantsNotificationType(?string $type, array $context = []): bool
    {
        $prefs = $this->notificationPreferences();

        switch ($type) {
            case 'new_post':
                $mode = $prefs['new_post_reminders'] ?? 'all';
                if ($mode === 'off') {
                    return false;
                }
                if ($mode === 'mutual') {
                    $authorId = $context['related_user_id'] ?? null;
                    // "mutual" = si pembuat postingan juga mengikuti saya (accepted).
                    return $authorId
                        ? $this->followers()->where('users.user_id', $authorId)
                            ->wherePivot('status', 'accepted')->exists()
                        : true;
                }
                return true;
            case 'like':
                return (bool) ($prefs['likes'] ?? true);
            case 'comment':
            case 'reply':
                return (bool) ($prefs['comments'] ?? true);
            case 'mention':
            case 'bio_mention':
            case 'story_mention':
                return (bool) ($prefs['mentions'] ?? true);
            case 'follow':
                return (bool) ($prefs['follows'] ?? true);
            // follow_request & follow_accepted selalu tampil (penting/aktvariabel), tak digate.
            default:
                return true;
        }
    }

    /**
     * Agar Laravel pakai "password_hash" untuk login
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getProfilePictureUrlAttribute($value): ?string
    {
        return $this->normalizeDefaultMediaUrl($value, 'default-profile.png');
    }

    public function getBannerUrlAttribute($value): ?string
    {
        return $this->normalizeDefaultMediaUrl($value, 'default-banner.png');
    }

    private function normalizeDefaultMediaUrl($value, string $defaultFile): ?string
    {
        if (!$value) {
            return null;
        }

        $path = parse_url((string) $value, PHP_URL_PATH) ?: (string) $value;

        return str_ends_with($path, "/{$defaultFile}") ? null : (string) $value;
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

public function bookmarks()
{
    return $this->hasMany(Bookmark::class, 'user_id');
}


public function bookmarkedPosts()
{
    return $this->belongsToMany(Post::class, 'bookmarks', 'user_id', 'post_id')
                ->withTimestamps();
}

public function loginHistories()
{
    return $this->hasMany(LoginHistory::class);
}

public function commentLikes()
{
    return $this->hasMany(CommentLike::class, 'user_id', 'user_id');
}

public function groups()
{
    return $this->belongsToMany(Group::class, 'group_members', 'user_id', 'group_id')
                ->withPivot('role', 'joined_at', 'is_muted');
}



}
