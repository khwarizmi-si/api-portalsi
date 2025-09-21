<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoginHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_id',
        'ip_address',
        'user_agent',
        'device',
        'browser',
        'platform',
        'login_at',
    ];

    protected $casts = [
        'login_at' => 'datetime',
    ];

    // ✅ Tambahkan boot method untuk validasi
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->user_id)) {
                throw new \Exception('User ID is required for login history');
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** apakah entry ini boleh dihapus oleh user? (boleh kalau sudah >= 7 hari) */
    public function isDeletable(): bool
    {
        return $this->login_at->lte(now()->subDays(7));
    }
}