<?php

namespace App\Jobs;

use App\Models\LlmRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordLlmRequestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array{user_id: int|null, model: string|null, input_tokens: int, output_tokens: int, openai_id: string|null, region: string|null}  $payload
     */
    public function __construct(
        protected array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        LlmRequest::query()->create($this->payload);
    }
}
