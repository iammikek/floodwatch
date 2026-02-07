<?php

namespace App\Models;

use Database\Factories\LocationBookmarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options) {
            if ($this->is_default && $this->user_id) {
                static::where('user_id', $this->user_id)->lockForUpdate()->get();
                $query = static::where('user_id', $this->user_id);
                if ($this->exists) {
                    $query->where('id', '!=', $this->getKey());
                }
                $query->update(['is_default' => false]);
            }

            return parent::save($options);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
