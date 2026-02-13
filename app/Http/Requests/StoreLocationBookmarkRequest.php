<?php

namespace App\Http\Requests;

use App\Services\LocationResolver;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationBookmarkRequest extends FormRequest
{
    protected $errorBag = 'bookmark-store';

    /**
     * Resolved location data from LocationResolver (set during validation).
     *
     * @var array{valid: bool, in_area: bool, lat?: float, lng?: float, region?: string|null, display_name?: string}|null
     */
    public ?array $resolvedLocation = null;

    /**
     * {@inheritdoc}
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        $max = config('flood-watch.bookmarks_max_per_user', 10);

        return [
            'label' => ['required', 'string', 'max:50'],
            'location' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($max): void {
                    $user = $this->user();
                    if ($user === null) {
                        $fail(__('flood-watch.bookmarks.auth_required'));

                        return;
                    }
                    if ($user->locationBookmarks()->count() >= $max) {
                        $fail(__('flood-watch.bookmarks.max_reached', ['max' => $max]));

                        return;
                    }
                    $resolver = app(LocationResolver::class);
                    $resolved = $resolver->resolve(trim($value));
                    if (! $resolved['valid']) {
                        $fail($resolved['error'] ?? __('flood-watch.errors.invalid_location'));

                        return;
                    }
                    if (! $resolved['in_area']) {
                        $fail($resolved['error'] ?? __('flood-watch.errors.outside_area'));

                        return;
                    }
                    if (! isset($resolved['lat'], $resolved['lng'])) {
                        $fail(__('flood-watch.bookmarks.unable_to_resolve'));

                        return;
                    }
                    $this->resolvedLocation = $resolved;
                },
            ],
        ];
    }

    /**
     * Get the display name for the resolved location.
     */
    public function resolvedDisplayName(): string
    {
        $resolved = $this->resolvedLocation;

        return $resolved['display_name'] ?? trim($this->input('location', ''));
    }
}
