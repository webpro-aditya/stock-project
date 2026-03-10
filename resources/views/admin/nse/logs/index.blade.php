@extends('layouts.user_type.auth')

@section('page_title', __('Extranet Sync Logs - ' . Str::upper($segment)))

@section('header-title')
<span>NSE {{ Str::ucfirst($type) }} Segment Logs</span>
@endsection

@section('content')
<main class="flex-1 p-6 bg-gray-50">

    @php
    $segments = ['CM', 'CD', 'CO', 'FO'];
    @endphp

    {{-- Segment Tabs --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="flex border-b border-gray-200">
            @foreach($segments as $seg)
            <a href="{{ route('nse.logs.index', ['type' => $type, 'segment' => $seg]) }}"
                class="px-6 py-3 text-sm font-semibold transition-colors
                   {{ $segment === $seg 
                        ? 'border-b-2 border-brand text-brand bg-gray-50' 
                        : 'text-gray-600 hover:text-brand hover:bg-gray-50' }}">
                {{ $seg }}
            </a>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-800">
                {{ $segment }} Logs ({{ $logs->total() }})
            </h2>

            <div class="text-xs text-gray-500">
                Showing {{ $logs->firstItem() ?? 0 }} – {{ $logs->lastItem() ?? 0 }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">#</th>
                        <th class="px-6 py-3">Message</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Created At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-700">
                            {{ $log->id }}
                        </td>

                        <td class="px-6 py-3 text-gray-600">
                            {{ $log->message ?? '-' }}
                        </td>

                        <td class="px-6 py-3">
                            @php $status = strtolower($log->statuscode ?? 'unknown'); @endphp
                            <span class="px-2 py-1 text-xs font-semibold rounded
                                {{ $status === 'success' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $status === 'running' ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>

                        <td class="px-6 py-3 text-gray-500">
                            {{ optional($log->created_at)->format('d M Y H:i:s') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                            No logs found for {{ $segment }}.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($logs->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
            {{ $logs->links('pagination::tailwind') }}
        </div>
        @endif
    </div>

</main>
@endsection