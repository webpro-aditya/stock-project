@extends('layouts.user_type.auth')

@section('page_title', __('Extranet Sync - ' . Str::upper($segment)))

@php
    $folder = trim($folder ?? '', '/');
@endphp

@section('style')
<style>
    #syncProgressWrapper { width: 100%; height: 5px; margin: 0 !important; }

    @keyframes badge-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
        40%            { transform: translateY(-4px); opacity: 1; }
    }
    #syncDots span { animation: badge-bounce 1.2s ease-in-out infinite; }
    #syncStatusBadge, #syncDoneBadge { transition: opacity 0.25s ease; }
</style>
@endsection

@section('header-title')
<span>NSE Common Segment</span>
@endsection

@section('header-actions')
<div class="flex flex-col items-end gap-1.5">
    <button onclick="syncNow('{{ $segment }}', '{{ $folder }}')"
        class="btn-sync flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-lg shadow-sm transition-all active:scale-95">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        <span>SYNC NOW</span>
    </button>

    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium mr-1">
        @if($lastSynced && \Carbon\Carbon::parse($lastSynced)->timezone('Asia/Kolkata')->isToday())
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
        @else
            <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
        @endif
        <span>
            Last synced:
            @if($lastSynced)
                {{ \Carbon\Carbon::parse($lastSynced)->format('h:i a') }}
            @endif
        </span>
    </div>
</div>
@endsection

@section('content')
<main class="flex-1 p-6 bg-gray-50">

    {{-- Breadcrumb --}}
    <nav class="p-2 text-sm font-medium text-gray-600">
        <ol class="flex items-center gap-2 flex-wrap">
            <li>NSE Common Segment</li>
            <li class="text-gray-400">/</li>

            <li>
                <a href="{{ route('nse.common.segment.folder.today', ['segment' => $segment, 'folder' => 'root']) }}"
                   class="hover:text-brand font-semibold">
                    {{ Str::upper($segment) }}
                </a>
            </li>

            @php
                $rawFolderParam  = request()->query('folder');
                $folderParts     = array_filter(explode('/', $rawFolderParam));
                $accumulatedPath = '';
            @endphp

            @foreach($folderParts as $part)
                @php $accumulatedPath .= ($accumulatedPath ? '/' : '') . $part; @endphp
                <li class="text-gray-400">/</li>
                <li>
                    <a href="{{ route('nse.common.segment.folder.today', ['segment' => $segment, 'folder' => 'root']) }}?folder={{ $accumulatedPath }}"
                       class="hover:text-brand font-semibold">
                        {{ $part }}
                    </a>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow-lg">
        <div class="px-6 py-3 border-b border-gray-200 flex justify-between items-center">
            <div class="text-lg font-bold text-gray-900">
                All Activity
            </div>

            <div id="syncStatusBadge" style="display:none;" class="text-xs text-indigo-600">
                Syncing...
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="text-sm text-left w-full">
                <thead class="bg-gray-100 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 w-12">
                            <input type="checkbox" onchange="toggleAll(this)">
                        </th>
                        <th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Created</th>
                        <th class="px-6 py-3">Updated</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>

                <tbody id="folderTableBody">
                    @include('admin.nse.common._folder_table_rows', [
                        'contents' => $contents,
                        'segment'  => $segment,
                        'folder'   => $folder,
                    ])
                </tbody>
            </table>

            {{-- ✅ FIXED PAGINATION --}}
            <div id="paginationWrapper" class="p-4">
                {{ $contents->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>

document.addEventListener('DOMContentLoaded', function () {
    triggerBackgroundSync();
});

// ✅ Background Sync ONLY (no table refresh)
function triggerBackgroundSync() {
    fetch("{{ route('nse.common.sync.background', ['segment' => $segment]) }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ folder: "{{ $folder }}" })
    })
    .then(res => res.json())
    .then(data => {
        console.log('Sync done:', data);

        // ❌ DO NOT refresh table
        // ❌ DO NOT call refreshFolderTable()

        // Optional: show small UI feedback only
        const badge = document.getElementById('syncStatusBadge');
        if (badge) {
            badge.innerText = "Updated";
            badge.style.display = 'inline';
            setTimeout(() => badge.style.display = 'none', 3000);
        }
    })
    .catch(err => {
        console.error("Sync error:", err);
    });
}
</script>
@endsection