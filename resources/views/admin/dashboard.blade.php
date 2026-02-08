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
                @if ($llmUsage['error'] ?? null)
                    <p class="text-sm text-amber-600">{{ $llmUsage['error'] }}</p>
                @else
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                            <dt class="text-sm font-medium text-gray-700">Requests today</dt>
                            <dd class="text-gray-900">{{ number_format($llmUsage['requests_today']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                            <dt class="text-sm font-medium text-gray-700">Requests this month</dt>
                            <dd class="text-gray-900">{{ number_format($llmUsage['requests_this_month']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                            <dt class="text-sm font-medium text-gray-700">Input tokens (this month)</dt>
                            <dd class="text-gray-900">{{ number_format($llmUsage['input_tokens_this_month'] ?? 0) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                            <dt class="text-sm font-medium text-gray-700">Output tokens (this month)</dt>
                            <dd class="text-gray-900">{{ number_format($llmUsage['output_tokens_this_month'] ?? 0) }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                            <dt class="text-sm font-medium text-gray-700">Est. cost this month</dt>
                            <dd class="text-gray-900">${{ number_format($llmUsage['cost_this_month'] ?? 0, 2) }}</dd>
                        </div>
                        @if (($llmUsage['remaining_budget'] ?? null) !== null)
                            <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                                <dt class="text-sm font-medium text-gray-700">Remaining (est.)</dt>
                                <dd class="text-gray-900">${{ number_format($llmUsage['remaining_budget'], 2) }}</dd>
                            </div>
                        @endif
                        @if ($budgetMonthly > 0)
                            <div class="flex items-center justify-between rounded-lg border px-4 py-3">
                                <dt class="text-sm font-medium text-gray-700">Monthly budget</dt>
                                <dd class="text-gray-900">${{ number_format($budgetMonthly, 2) }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($isOverBudgetAlert)
                        <p class="mt-4 text-sm font-medium text-amber-600">
                            Budget alert: Estimated spend is at or above 80% of monthly budget.
                        </p>
                    @endif
                    @if (!empty($llmUsage['chart_daily'] ?? []))
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Usage this month</h4>
                            <div class="relative h-64">
                                <canvas id="llm-usage-chart"></canvas>
                            </div>
                        </div>
                    @endif
                @endif
            </section>

            <section class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Recent LLM Requests</h3>
                @if ($recentLlmRequests->isEmpty())
                    <p class="text-sm text-gray-500">No LLM requests recorded yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Input</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Output</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($recentLlmRequests as $req)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">{{ $req->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ $req->model ?? '—' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700 text-right">{{ number_format($req->input_tokens) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700 text-right">{{ number_format($req->output_tokens) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ $req->region ?? '—' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-700">{{ $req->user?->email ?? 'Guest' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @if (!empty($llmUsage['chart_daily'] ?? []))
        <script type="application/json" id="chart-data">@json($llmUsage['chart_daily'])</script>
        @vite(['resources/js/admin-chart.js'])
    @endif
</x-app-layout>
