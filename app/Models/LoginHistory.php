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
        // Ubah dari throw exception ke set default/null
        if (empty($model->user_id)) {
            // Log warning tapi jangan halt process
            \Log::warning('LoginHistory created without user_id', [
                'ip' => $model->ip_address,
                'user_agent' => $model->user_agent
            ]);
            
            // Optionally set a default or leave null jika column nullable
            // $model->user_id = 0; // atau null jika column nullable
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