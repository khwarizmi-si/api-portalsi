<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SearchHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'target_user_id',
        'type',
        'query',
        'query_key',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id', 'user_id');
    }
}
