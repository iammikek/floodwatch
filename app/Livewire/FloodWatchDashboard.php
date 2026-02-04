<?php

namespace App\Livewire;

use App\Services\SomersetAssistantService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FloodWatchDashboard extends Component
{
    public string $postcode = '';

    public bool $loading = false;

    public ?string $assistantResponse = null;

    public array $floods = [];

    public array $incidents = [];

    public ?string $lastChecked = null;

    public ?string $error = null;

    public function search(SomersetAssistantService $assistant): void
    {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'lastChecked', 'error']);
        $this->loading = true;

        $message = $this->postcode
            ? "Check flood and road status for postcode {$this->postcode} in the Somerset Levels."
            : 'Check flood and road status for the Somerset Levels.';

        $cacheKey = trim($this->postcode) !== '' ? $this->postcode : null;

        try {
            $result = $assistant->chat($message, [], $cacheKey);
            $this->assistantResponse = $result['response'];
            $this->floods = $result['floods'];
            $this->incidents = $result['incidents'];
            $this->lastChecked = $result['lastChecked'] ?? null;
        } catch (\Throwable $e) {
            report($e);
            $this->error = config('app.debug')
                ? $e->getMessage()
                : 'Unable to get a response. Please try again.';
        } finally {
            $this->loading = false;
        }
    }

    public function render()
    {
        return view('livewire.flood-watch-dashboard');
    }
}
