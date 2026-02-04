<?php

namespace App\Livewire;

use App\Services\PostcodeValidator;
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

    public function search(SomersetAssistantService $assistant, PostcodeValidator $postcodeValidator): void
    {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'lastChecked', 'error']);
        $this->loading = true;

        $postcodeTrimmed = trim($this->postcode);

        $validation = null;
        if ($postcodeTrimmed !== '') {
            $validation = $postcodeValidator->validate($postcodeTrimmed, geocode: true);
            if (! $validation['valid']) {
                $this->error = $validation['error'] ?? 'Invalid postcode.';
                $this->loading = false;

                return;
            }
            if (! $validation['in_area']) {
                $this->error = $validation['error'] ?? 'This postcode is outside the Somerset Levels.';
                $this->loading = false;

                return;
            }
        }

        $message = $this->buildMessage($postcodeTrimmed, $validation);
        $cacheKey = $postcodeTrimmed !== '' ? $postcodeTrimmed : null;

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

    /**
     * @param  array{lat?: float, long?: float, outcode?: string}|null  $validation
     */
    private function buildMessage(string $postcode, ?array $validation): string
    {
        if ($postcode === '') {
            return 'Check flood and road status for the Somerset Levels.';
        }

        $normalized = app(PostcodeValidator::class)->normalize($postcode);
        $coords = '';
        if ($validation !== null && isset($validation['lat'], $validation['long'])) {
            $coords = sprintf(' (lat: %.4f, long: %.4f)', $validation['lat'], $validation['long']);
        }

        return "Check flood and road status for postcode {$normalized}{$coords} in the Somerset Levels.";
    }

    public function render()
    {
        return view('livewire.flood-watch-dashboard');
    }
}
