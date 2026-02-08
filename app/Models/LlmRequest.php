<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmRequest extends Model
{
    /** @use HasFactory<\Database\Factories\LlmRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'model',
        'input_tokens',
        'output_tokens',
        'openai_id',
        'region',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
