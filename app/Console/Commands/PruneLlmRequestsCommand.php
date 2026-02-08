<?php

namespace App\Console\Commands;

use App\Models\LlmRequest;
use Illuminate\Console\Command;

class PruneLlmRequestsCommand extends Command
{
    protected $signature = 'flood-watch:prune-llm-requests
                            {--days= : Override retention days from config}';

    protected $description = 'Prune LLM request records older than the configured retention period';

    public function handle(): int
    {
        $retentionDays = (int) ($this->option('days') ?? config('flood-watch.llm_requests_retention_days', 90));

        if ($retentionDays <= 0) {
            $this->info('LLM request pruning is disabled (retention days ≤ 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($retentionDays);
        $deleted = LlmRequest::query()->where('created_at', '<', $cutoff)->delete();

        $this->info(sprintf('Pruned %d LLM request records older than %s.', $deleted, $cutoff->toDateString()));

        return self::SUCCESS;
    }
}
