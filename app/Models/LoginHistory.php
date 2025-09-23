<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Carbon\Carbon;

class LoginHistory extends Model
{
    use HasFactory;

    protected $table = 'login_histories';

    /**
     * Mass assignable fields
     */
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

    /**
     * Cast fields to proper types
     */
    protected $casts = [
        'login_at' => 'datetime',
    ];

    /**
     * Relasi ke user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Optional helper: format login time
     */
    public function getFormattedLoginAtAttribute()
    {
        return $this->login_at ? Carbon::parse($this->login_at)->format('Y-m-d H:i:s') : null;
    }
}
