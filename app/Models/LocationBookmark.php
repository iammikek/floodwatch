<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationBookmark extends Model
{
    /** @use HasFactory<\Database\Factories\LocationBookmarkFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'location',
        'lat',
        'lng',
        'region',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (LocationBookmark $bookmark): void {
            // When is_default=true, clear other defaults so only one per user.
            // Runs after save succeeds to avoid leaving user with no default if save fails.
            if ($bookmark->is_default && $bookmark->user_id) {
                static::where('user_id', $bookmark->user_id)
                    ->where('id', '!=', $bookmark->getKey())
                    ->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
