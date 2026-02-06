<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemActivity extends Model
{
    /** @use HasFactory<\Database\Factories\SystemActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'description',
        'severity',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public static function recent(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }
}
