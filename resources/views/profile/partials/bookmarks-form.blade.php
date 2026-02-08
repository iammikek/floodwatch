<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('flood-watch.dashboard.bookmarks') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('flood-watch.bookmarks.intro') }}
        </p>
    </header>

    @if (session('status') === 'bookmark-created')
        <p
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 3000)"
            class="mt-4 text-sm text-green-600"
        >{{ __('flood-watch.bookmarks.created') }}</p>
    @endif
    @if (session('status') === 'bookmark-updated')
        <p
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 3000)"
            class="mt-4 text-sm text-green-600"
        >{{ __('flood-watch.bookmarks.updated') }}</p>
    @endif
    @if (session('status') === 'bookmark-deleted')
        <p
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 3000)"
            class="mt-4 text-sm text-green-600"
        >{{ __('flood-watch.bookmarks.deleted') }}</p>
    @endif

    @if ($user->locationBookmarks->isEmpty())
        <p class="mt-4 text-sm text-gray-600">{{ __('flood-watch.bookmarks.no_bookmarks') }}</p>
    @else
        <ul class="mt-4 space-y-2">
            @foreach ($user->locationBookmarks as $bookmark)
                @php
                    $isEditing = session('editing_bookmark_id') === $bookmark->id;
                @endphp
                <li
                    x-data="{ editing: @js($isEditing) }"
                    class="p-3 rounded-lg bg-gray-50 border border-gray-200 space-y-3"
                >
                    <div x-show="!editing" class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <span class="font-medium text-gray-900">{{ $bookmark->label }}</span>
                            <span class="text-gray-500">· {{ $bookmark->location }}</span>
                            @if ($bookmark->is_default)
                                <span class="ml-2 text-xs font-medium text-blue-600">{{ __('flood-watch.bookmarks.default') }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" @click="editing = true" class="text-sm text-blue-600 hover:text-blue-800">
                                {{ __('flood-watch.dashboard.edit') }}
                            </button>
                            @if (! $bookmark->is_default)
                                <form method="post" action="{{ route('bookmarks.update', $bookmark) }}" class="inline">
                                    @csrf
                                    @method('patch')
                                    <input type="hidden" name="is_default" value="1" />
                                    <button type="submit" class="text-sm text-blue-600 hover:text-blue-800">
                                        {{ __('flood-watch.dashboard.set_as_default') }}
                                    </button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('bookmarks.destroy', $bookmark) }}" class="inline" onsubmit="return confirm('{{ __('flood-watch.dashboard.delete_bookmark_confirm') }}');">
                                @csrf
                                @method('delete')
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">
                                    {{ __('flood-watch.dashboard.delete') }}
                                </button>
                            </form>
                        </div>
                    </div>
                    <form x-show="editing" x-cloak method="post" action="{{ route('bookmarks.update', $bookmark) }}" class="space-y-4" @submit="editing = false">
                        @csrf
                        @method('patch')
                        <div>
                            <x-input-label for="edit_bookmark_label_{{ $bookmark->id }}" :value="__('flood-watch.bookmarks.label')" />
                            <x-text-input
                                id="edit_bookmark_label_{{ $bookmark->id }}"
                                name="label"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('label', $bookmark->label)"
                                :placeholder="__('flood-watch.bookmarks.label_placeholder')"
                                required
                                maxlength="50"
                            />
                            <x-input-error class="mt-2" :messages="$errors->getBag('bookmark-update')->get('label')" />
                        </div>
                        <div>
                            <x-input-label for="edit_bookmark_location_{{ $bookmark->id }}" :value="__('flood-watch.bookmarks.location')" />
                            <x-text-input
                                id="edit_bookmark_location_{{ $bookmark->id }}"
                                name="location"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('location', $bookmark->location)"
                                :placeholder="__('flood-watch.bookmarks.location_placeholder')"
                                maxlength="255"
                            />
                            <x-input-error class="mt-2" :messages="$errors->getBag('bookmark-update')->get('location')" />
                        </div>
                        <div class="flex gap-2">
                            <x-primary-button type="submit">{{ __('flood-watch.bookmarks.save') }}</x-primary-button>
                            <button type="button" @click="editing = false" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                {{ __('flood-watch.bookmarks.cancel') }}
                            </button>
                        </div>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($user->locationBookmarks->count() < config('flood-watch.bookmarks_max_per_user', 10))
        <form method="post" action="{{ route('bookmarks.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <x-input-label for="bookmark_label" :value="__('flood-watch.bookmarks.label')" />
                <x-text-input
                    id="bookmark_label"
                    name="label"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('label')"
                    :placeholder="__('flood-watch.bookmarks.label_placeholder')"
                    required
                    maxlength="50"
                />
                <x-input-error class="mt-2" :messages="$errors->getBag('bookmark-store')->get('label')" />
            </div>

            <div>
                <x-input-label for="bookmark_location" :value="__('flood-watch.bookmarks.location')" />
                <x-text-input
                    id="bookmark_location"
                    name="location"
                    type="text"
                    class="mt-1 block w-full"
                    :value="old('location')"
                    :placeholder="__('flood-watch.bookmarks.location_placeholder')"
                    required
                    maxlength="255"
                />
                <x-input-error class="mt-2" :messages="$errors->getBag('bookmark-store')->get('location')" />
            </div>

            <x-primary-button>{{ __('flood-watch.bookmarks.add') }}</x-primary-button>
        </form>
    @else
        <p class="mt-4 text-sm text-gray-600">{{ __('flood-watch.bookmarks.max_reached', ['max' => config('flood-watch.bookmarks_max_per_user', 10)]) }}</p>
    @endif
</section>
