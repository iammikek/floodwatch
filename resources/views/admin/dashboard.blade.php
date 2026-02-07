<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">API Health</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($checks as $name => $check)
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                            <dt class="text-sm font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $name)) }}</dt>
                            <dd class="flex items-center gap-2">
                                @if (($check['status'] ?? '') === 'ok')
                                    <span class="text-green-600">✓ ok</span>
                                @elseif (($check['status'] ?? '') === 'skipped')
                                    <span class="text-gray-400">skipped</span>
                                @else
                                    <span class="text-red-600">✗ {{ $check['status'] ?? 'failed' }}</span>
                                @endif
                                @if (!empty($check['message']))
                                    <span class="text-xs text-gray-500" title="{{ $check['message'] }}">ⓘ</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">User Metrics</h3>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                        <dt class="text-sm font-medium text-gray-700">Total users</dt>
                        <dd class="text-gray-900">{{ number_format($totalUsers) }}</dd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                        <dt class="text-sm font-medium text-gray-700">Total searches</dt>
                        <dd class="text-gray-900">{{ number_format($totalSearches) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">LLM Cost</h3>
                <p class="text-sm text-gray-500">Requests and budget tracking coming soon.</p>
            </section>
        </div>
    </div>
</x-app-layout>
