<?php

namespace App\Services;

class SearchMessageBuilder
{
    /**
     * Build the user message for the flood watch search.
     *
     * @param  array{lat?: float, long?: float, outcode?: string, display_name?: string}|null  $validation
     */
    public function build(string $location, ?array $validation): string
    {
        if ($location === '') {
            return __('flood-watch.message.check_status_default');
        }

        $label = $validation['display_name'] ?? $location;
        $coords = '';
        if ($validation !== null && isset($validation['lat'], $validation['long'])) {
            $coords = sprintf(' (lat: %.4f, long: %.4f)', $validation['lat'], $validation['long']);
        }

        return __('flood-watch.message.check_status_location', ['label' => $label, 'coords' => $coords]);
    }
}
