<?php

namespace App\Models;

use Database\Factories\UserSearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSearch extends Model
{
    /** @use HasFactory<UserSearchFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'location',
        'lat',
        'lng',
        'region',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
