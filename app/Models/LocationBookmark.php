<?php

namespace App\Models;

use Database\Factories\LocationBookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationBookmark extends Model
{
    /** @use HasFactory<LocationBookmarkFactory> */
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
        static::saving(function (self $model) {
            if ($model->is_default && $model->user_id) {
                $query = static::where('user_id', $model->user_id);
                if ($model->exists) {
                    $query->where('id', '!=', $model->getKey());
                }
                $query->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
