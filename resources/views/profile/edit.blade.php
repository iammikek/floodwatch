<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.bookmarks-form')
                </div>
            </div>

            @if (config('flood-watch.donation_url'))
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <p class="text-sm font-medium text-slate-700">{{ __('flood-watch.donations.support_title') }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ __('flood-watch.donations.support_intro') }}</p>
                    <a href="{{ config('flood-watch.donation_url') }}" target="_blank" rel="noopener" class="text-blue-600 mt-2 inline-block font-medium hover:underline">{{ __('flood-watch.donations.donate') }} →</a>
                </div>
            </div>
            @endif

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
