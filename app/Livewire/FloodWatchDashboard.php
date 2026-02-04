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

    public array $forecast = [];

    public array $weather = [];

    public ?string $lastChecked = null;

    public ?string $error = null;

    public function search(SomersetAssistantService $assistant, PostcodeValidator $postcodeValidator): void
    {
        $this->reset(['assistantResponse', 'floods', 'incidents', 'forecast', 'weather', 'lastChecked', 'error']);
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
                $this->error = $validation['error'] ?? 'This postcode is outside the South West.';
                $this->loading = false;

                return;
            }
        }

        $message = $this->buildMessage($postcodeTrimmed, $validation);
        $cacheKey = $postcodeTrimmed !== '' ? $postcodeTrimmed : null;
        $userLat = $validation['lat'] ?? null;
        $userLong = $validation['long'] ?? null;
        $region = $validation['region'] ?? null;

        try {
            $result = $assistant->chat($message, [], $cacheKey, $userLat, $userLong, $region);
            $this->assistantResponse = $result['response'];
            $this->floods = collect($result['floods'])->sortByDesc(fn (array $f) => $f['timeMessageChanged'] ?? $f['timeRaised'] ?? '')->values()->all();
            $this->incidents = $result['incidents'];
            $this->forecast = $result['forecast'] ?? [];
            $this->weather = $result['weather'] ?? [];
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
            return 'Check flood and road status for the South West (Bristol, Somerset, Devon, Cornwall).';
        }

        $normalized = app(PostcodeValidator::class)->normalize($postcode);
        $coords = '';
        if ($validation !== null && isset($validation['lat'], $validation['long'])) {
            $coords = sprintf(' (lat: %.4f, long: %.4f)', $validation['lat'], $validation['long']);
        }

        return "Check flood and road status for postcode {$normalized}{$coords} in the South West.";
    }

    public function render()
    {
        return view('livewire.flood-watch-dashboard');
    }
}
