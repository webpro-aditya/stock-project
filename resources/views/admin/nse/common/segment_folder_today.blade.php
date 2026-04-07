@extends('layouts.user_type.auth')

@section('page_title', __('Extranet Sync - ' . Str::upper($segment)))

@php
$folder = trim($folder ?? '', '/');
@endphp

@section('style')
<style>
    #syncProgressWrapper {
        width: 100%;
        height: 5px;
        margin: 0 !important;
    }

    @keyframes badge-bounce {

        0%,
        80%,
        100% {
            transform: translateY(0);
            opacity: 0.4;
        }

        40% {
            transform: translateY(-4px);
            opacity: 1;
        }
    }

    #syncDots span {
        animation: badge-bounce 1.2s ease-in-out infinite;
    }

    #syncStatusBadge,
    #syncDoneBadge {
        transition: opacity 0.25s ease;
    }

    #syncStatusBadge {
        background: #eee;
        color: #333;
        padding: 4px 8px;
        border-radius: 4px;
    }

    tbody tr:nth-child(even) {
        background: #F7F7F7 !important;
    }

    td:nth-child(2) {
        width: 32% !important;
    }
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

{{-- Bulk action bar --}}
<div id="bulkActionBar"
    class="absolute bottom-6 -translate-x-1/2 flex items-center gap-6 py-2 px-3 pl-5 rounded-full bg-gray-900 shadow-2xl shadow-gray-900/50 border border-gray-800 transition-all duration-300 translate-y-[150%] opacity-0"
    style="left: 60%">
    <div class="flex items-center gap-3 text-white">
        <div id="selectedCount" class="w-7 h-7 text-sm font-bold flex items-center justify-center bg-brand rounded-full">0</div>
        <span class="font-semibold">Items Selected</span>
    </div>
    <button class="flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-full transition-colors btn-bulk-action"
        onclick="downloadSelected()">
        <i data-lucide="download-cloud" class="w-4 h-4"></i> Download All
    </button>
    <button onclick="clearSelection()" class="text-gray-500 hover:text-gray-300 p-1 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
    </button>
</div>
@endsection

@section('content')
<main class="flex-1 p-6 bg-gray-50">

    {{-- Breadcrumb --}}
    <nav class="p-2 text-sm text-gray-600">
        <ol class="flex items-center gap-2 flex-wrap">
            <li>NSE Common Segment</li>
            <li>/</li>

            <li>
                <a href="{{ route('nse.common.segment.folder.today', ['segment' => $segment, 'folder' => 'root']) }}">
                    {{ Str::upper($segment) }}
                </a>
            </li>

            @php
            $rawFolderParam = request()->query('folder');
            $folderParts = array_filter(explode('/', $rawFolderParam));
            $accumulatedPath = '';
            @endphp

            @foreach($folderParts as $part)
            @php $accumulatedPath .= ($accumulatedPath ? '/' : '') . $part; @endphp
            <li>/</li>
            <li>
                <a href="{{ route('nse.common.segment.folder.today', ['segment' => $segment, 'folder' => 'root']) }}?folder={{ $accumulatedPath }}">
                    {{ $part }}
                </a>
            </li>
            @endforeach
        </ol>
    </nav>

    <div class="bg-white rounded-lg shadow-lg">

        <div class="px-6 py-3 border-b flex justify-between">
            <div class="flex items-center gap-3 text-lg font-bold text-gray-900">
                <i data-lucide="sun" class="w-6 h-6 text-amber-500"></i>
                All Activity
            </div>
            <div id="syncStatusBadge" style="display:none;">Syncing...</div>
        </div>

        {{-- Search --}}
        <form method="GET" class="p-4 flex gap-3">
            <input type="hidden" name="folder" value="{{ request('folder') }}">

            <input type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search..."
                class="border px-3 py-2 rounded">

            <button class="bg-brand text-white px-4 py-2 rounded">
                Search
            </button>

            @if(request('search'))
            <a href="{{ request()->url() }}?folder={{ request('folder') }}">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" onchange="toggleAll(this)" class="w-4 h-4 rounded border-gray-300">
                        </th>

                        <th class="px-6 py-3 text-left">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                class="inline-flex items-center gap-1.5 hover:text-gray-800 transition-colors">
                                Folder / File Name
                                @if(request('sort') == 'name')
                                <i data-lucide="{{ request('direction') == 'asc' ? 'arrow-up' : 'arrow-down' }}"
                                    class="w-3.5 h-3.5 text-brand"></i>
                                @else
                                <i data-lucide="arrow-up-down" class="w-3.5 h-3.5 text-gray-300"></i>
                                @endif
                            </a>
                        </th>

                        <th class="px-6 py-3 text-left">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'nse_created_at', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                class="inline-flex items-center gap-1.5 hover:text-gray-800 transition-colors">
                                Created
                                @if(request('sort') == 'nse_created_at')
                                <i data-lucide="{{ request('direction') == 'asc' ? 'arrow-up' : 'arrow-down' }}"
                                    class="w-3.5 h-3.5 text-brand"></i>
                                @else
                                <i data-lucide="arrow-up-down" class="w-3.5 h-3.5 text-gray-300"></i>
                                @endif
                            </a>
                        </th>

                        <th class="px-6 py-3 text-left">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'nse_modified_at', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}"
                                class="inline-flex items-center gap-1.5 hover:text-gray-800 transition-colors">
                                Last Updated
                                @if(request('sort') == 'nse_modified_at')
                                <i data-lucide="{{ request('direction') == 'asc' ? 'arrow-up' : 'arrow-down' }}"
                                    class="w-3.5 h-3.5 text-brand"></i>
                                @else
                                <i data-lucide="arrow-up-down" class="w-3.5 h-3.5 text-gray-300"></i>
                                @endif
                            </a>
                        </th>

                        <th class="px-6 py-3 text-center">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @include('admin.nse.common._folder_table_rows', [
                    'contents' => $contents,
                    'segment' => $segment,
                    'folder' => $folder,
                    ])
                </tbody>
            </table>

            <div class="p-4">
                {{ $contents->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        triggerBackgroundSync();
    });

    function triggerBackgroundSync() {

        // ✅ prevent multiple reload loops
        if (sessionStorage.getItem('sync_reloaded') === '1') {
            sessionStorage.removeItem('sync_reloaded');
            return;
        }

        fetch("{{ route('nse.common.sync.background', ['segment' => $segment]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    folder: "{{ $folder }}"
                })
            })
            .then(res => res.json())
            .then(data => {

                // ✅ reload ONLY once when changes exist
                if (data.status === 'ok' && data.hasChanges) {

                    const badge = document.getElementById('syncStatusBadge');

                    if (badge) {
                        badge.innerText = "Updating...";
                        badge.style.display = 'inline';
                    }

                    // ✅ mark before reload
                    sessionStorage.setItem('sync_reloaded', '1');

                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }

            })
            .catch(err => {
                console.error("Sync error:", err);
            });
    }

    function triggerDownload(btn, id) {
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-circle" class="w-4 h-4 animate-spin mr-2"></i>`;
        lucide.createIcons();

        const url = "{{ route('nse.common.file.prepare', ['id' => ':id']) }}".replace(':id', id);

        fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error();
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: 'Downloading...'
                    });
                    btn.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 text-success"></i>&nbsp;Downloaded`;
                    lucide.createIcons();
                    window.location.href = data.url;
                } else throw new Error();
            })
            .catch(() => {
                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed.',
                    text: 'Please retry after some time.',
                    timer: 5000
                });
                btn.innerHTML = `<i data-lucide="x" class="w-4 h-4 mr-2"></i>`;
                lucide.createIcons();
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }, 3000);
            });
    }

    function checkSelection() {
        const count = document.querySelectorAll('.row-selector:checked').length;
        const bar = document.getElementById('bulkActionBar');
        document.getElementById('selectedCount').innerText = count;
        count > 0 ?
            bar.classList.remove('translate-y-[150%]', 'opacity-0') :
            bar.classList.add('translate-y-[150%]', 'opacity-0');
    }

    function toggleAll(masterCheckbox) {
        document.querySelectorAll('.row-selector').forEach(cb => cb.checked = masterCheckbox.checked);
        checkSelection();
    }

    function clearSelection() {
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        checkSelection();
    }

    function syncNow(segment, folder) {

        const btn = document.querySelector('.btn-sync');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> SYNCING...';
        lucide.createIcons();

        fetch("{{ route('nse.common.sync.background', ['segment' => ':seg']) }}".replace(':seg', segment), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    folder: folder
                })
            })
            .then(res => res.json())
            .then(data => {

                btn.disabled = false;
                btn.innerHTML = originalHtml;
                lucide.createIcons();

                if (data.status === 'ok') {

                    // ✅ ONLY reload if changes exist
                    if (data.hasChanges) {

                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Changes detected. Updating...'
                        });

                        // ✅ prevent reload loop
                        sessionStorage.setItem('sync_reloaded', '1');

                        setTimeout(() => {
                            window.location.reload();
                        }, 600);

                    } else {
                        // ✅ no UI noise (as per your requirement)
                        // do nothing
                    }

                } else if (data.status === 'in_progress') {

                    Toast.fire({
                        icon: 'info',
                        title: 'Sync already in progress.'
                    });

                } else {

                    Toast.fire({
                        icon: 'error',
                        title: 'Sync failed. Please retry.'
                    });
                }

            })
            .catch(() => {

                btn.disabled = false;
                btn.innerHTML = originalHtml;
                lucide.createIcons();

                Toast.fire({
                    icon: 'error',
                    title: 'Something went wrong.'
                });
            });
    }

    function downloadSelected() {
        const selectedCheckboxes = document.querySelectorAll('.row-selector:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

        if (!selectedIds.length) {
            Toast.fire({
                icon: 'warning',
                title: 'No files selected'
            });
            return;
        }

        const btn = document.querySelector('.btn-bulk-action');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Zipping...';
        lucide.createIcons();

        fetch("{{ route('nse.common.download.bulk.prepare') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    ids: selectedIds
                })
            })
            .then(response => {
                if (!response.ok) return response.json().then(err => {
                    throw new Error(err.message);
                });
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.href = data.url;
                    Toast.fire({
                        icon: 'success',
                        title: 'Download started!'
                    });
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        lucide.createIcons();
                        clearSelection();
                    }, 2000);
                } else throw new Error();
            })
            .catch(() => {
                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed',
                    text: 'Please retry after some time.',
                    timer: 5000
                });
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                lucide.createIcons();
            });
    }
</script>
@endsection