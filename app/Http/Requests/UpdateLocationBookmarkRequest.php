<?php

namespace App\Http\Requests;

use App\Models\LocationBookmark;
use App\Services\LocationResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationBookmarkRequest extends FormRequest
{
    protected $errorBag = 'bookmark-update';

    /**
     * Resolved location data from LocationResolver (set during validation, when location is provided).
     *
     * @var array{valid: bool, in_area: bool, lat?: float, lng?: float, region?: string|null, display_name?: string}|null
     */
    public ?array $resolvedLocation = null;

    /**
     * {@inheritdoc}
     */
    public function authorize(): bool
    {
        $bookmark = $this->route('bookmark');

        return $bookmark instanceof LocationBookmark && $bookmark->user_id === $this->user()?->id;
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        $rules = [
            'label' => ['sometimes', 'required', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
        ];

        if ($this->filled('location')) {
            $rules['location'] = [
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $resolver = app(LocationResolver::class);
                    $resolved = $resolver->resolve(trim($value));
                    if (! $resolved['valid']) {
                        $fail($resolved['error'] ?? __('flood-watch.error.invalid_location'));

                        return;
                    }
                    if (! $resolved['in_area']) {
                        $fail($resolved['error'] ?? __('flood-watch.error.outside_area'));

                        return;
                    }
                    if (! isset($resolved['lat'], $resolved['lng'])) {
                        $fail(__('flood-watch.bookmarks.unable_to_resolve'));

                        return;
                    }
                    $this->resolvedLocation = $resolved;
                },
            ];
        }

        return $rules;
    }

    /**
     * Get the display name for the resolved location (when location was updated).
     */
    public function resolvedDisplayName(): ?string
    {
        $resolved = $this->resolvedLocation;

        return $resolved !== null ? ($resolved['display_name'] ?? trim($this->input('location', ''))) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function failedValidation(Validator $validator): void
    {
        $bookmark = $this->route('bookmark');

        if ($bookmark instanceof LocationBookmark) {
            session()->flash('editing_bookmark_id', $bookmark->id);
        }

        parent::failedValidation($validator);
    }
}
