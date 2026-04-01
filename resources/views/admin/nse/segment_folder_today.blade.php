@extends('layouts.user_type.auth')

@section('page_title', __('Extranet Sync - ' . Str::upper($segment)))

@php
$folder = trim($folder ?? '', '/');
$parts = $folder ? explode('/', $folder) : [];
$path = '';
@endphp

@section('style')
<style>
    #syncProgressWrapper {
        width: 100%;
        height: 5px;
        margin: 0 !important;
    }
</style>
@endsection

@section('header-title')
<span>NSE Member Segment</span>
@endsection

@section('header-timer')
<strong style="font-size: 12px; color: red;" id="countdown"></strong>
@endsection
@section('header-actions')
@if(session('success'))
<div id="toast" class="toast-success">
</div>
@endif

@if(session('error'))
<div id="toast" class="toast-error">
</div>
@endif
<div class="flex flex-col items-end gap-1.5">
    <button onclick="syncNow('{{ $segment }}', '{{ $folder }}')"
        class="btn-sync flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-lg shadow-sm transition-all active:scale-95 focus:ring-2 focus:ring-brand focus:ring-offset-1">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        <span>SYNC NOW</span>
    </button>


    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium mr-1">
        @if($lastSynced && \Carbon\Carbon::parse($lastSynced)->timezone('Asia/Kolkata')->isToday())
        <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_4px_rgba(34,197,94,0.6)]" title="Synced Today"></span>
        @else
        <span class="w-2 h-2 rounded-full bg-yellow-500" title="Synced Previously"></span>
        @endif

        <span>Last synced:
            @if ($lastSynced)
            {{ \Carbon\Carbon::parse($lastSynced)->format('h:i a')}}
            @endif
        </span>

    </div>
</div>
@endsection
@section('content')
<div id="syncProgressWrapper" class="hidden mt-3">
    <div class="w-full bg-gray-200 rounded-lg overflow-hidden">
        <div id="syncProgressBar"
            class="bg-brand text-xs font-semibold text-white text-center py-1 transition-all duration-300"
            style="width: 0%">
            0%
        </div>
    </div>
</div>
<main class="flex-1 p-6 bg-gray-50">
    <nav class="p-2 text-sm font-medium text-gray-600">
        <ol class="flex items-center gap-2 flex-wrap">
            <li>
                NSE
            </li>
            <li class="text-gray-400">/</li>
            <li>
                Member Segment
            </li>
            <li class="text-gray-400">/</li>
            <li>
                <a href="{{ route('nse.segment.folder.today', [
                                'segment' => $segment,
                                'folder' => 'root'
                            ]) }}"
                    class="hover:text-brand font-semibold">
                    {{ Str::upper($segment) }}
                </a>
            </li>

            @php
            $rawFolderParam = request()->query('folder');

            $folderParts = array_filter(explode('/', $rawFolderParam));

            $accumulatedPath = '';
            @endphp

            @foreach($folderParts as $part)
            @php
            $accumulatedPath .= ($accumulatedPath ? '/' : '') . $part;
            @endphp

            <li class="text-gray-400">/</li>

            <li>
                <a href="{{ route('nse.segment.folder.today', [
                                    'segment' => $segment,
                                    'folder' => 'root' // Base route param stays 'root'
                                ]) }}?folder={{ $accumulatedPath }}"
                    class="hover:text-brand font-semibold">
                    {{ $part }}
                </a>
            </li>
            @endforeach
        </ol>
    </nav>

    <div class="bg-white rounded-lg shadow-lg">
        <div class="px-6 py-3 border-b border-gray-200">
            <div class="flex items-center gap-3 text-lg font-bold text-gray-900">
                <i data-lucide="sun" class="w-6 h-6 text-amber-500"></i>
                All Activity
            </div>
        </div>


        <div class="relative overflow-x-auto">
            <table class="text-sm NseSegmentTable text-left" @if($contents->count() > 0) id="activityTable" @endif>
                <thead class="text-xs text-gray-700 font-bold uppercase bg-gray-100 sticky top-0">
                    <tr>
                        <th scope="col" class="px-4 py-3 w-12">
                            <input type="checkbox" onchange="toggleAll(this)"
                                class="w-4 h-4 custom-checkbox rounded border-gray-300">
                        </th>
                        <th scope="col" class="px-6 py-3" style="display: none;">Type</th>
                        <th scope="col" class="px-6 py-3 folder_col">Folder / File Name</th>
                        <th scope="col" class="px-6 py-3 CreatedDate">Created</th>
                        <th scope="col" class="px-6 py-3 LastUpdate">Last Updated</th>
                        <th scope="col" class="px-6 py-3 text-right action_col">Action</th>
                    </tr>
                </thead>
                {{-- In the table, replace the @forelse block with: --}}
                <tbody id="folderTableBody">
                    @include('admin.nse._folder_table_rows', [
                    'contents' => $contents,
                    'segment' => $segment,
                    'folder' => $folder,
                    ])
                </tbody>
            </table>
        </div>
    </div>

    {{--<div class="text-center py-4 border-t border-gray-100">
        <a href="{{ route('nse.segment.archives', ['segment' => $segment, 'folder' => 'root']) }}"
    class="inline-flex flex-col items-center gap-1 text-xs font-bold text-gray-500 uppercase tracking-wider hover:text-brand transition-colors">
    <div
        class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-200 group-hover:bg-brand-light shadow-sm border border-gray-200 transition-colors">
        <i data-lucide="arrow-up" class="w-5 h-5"></i>
    </div>
    Load Archive History
    </a>
    </div>--}}
</main>

<div id="bulkActionBar"
    class="absolute bottom-6 -translate-x-1/2 flex items-center gap-6 py-2 px-3 pl-5 rounded-full bg-gray-900 shadow-2xl shadow-gray-900/50 border border-gray-800 transition-all duration-300 translate-y-[150%] opacity-0" style="left: 60%">
    <div class="flex items-center gap-3 text-white">
        <div id="selectedCount"
            class="w-7 h-7 text-sm font-bold flex items-center justify-center bg-brand rounded-full">0</div>
        <span class="font-semibold">Items Selected</span>
    </div>
    <button
        class="flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-full transition-colors btn-bulk-action" onclick="downloadSelected()">
        <i data-lucide="download-cloud" class="w-4 h-4"></i>
        Download All
    </button>
    <button onclick="clearSelection()"
        class="text-gray-500 hover:text-gray-300 p-1 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
    </button>
</div>
<span id="syncStatusBadge" class="hidden flex items-center gap-1 text-xs text-blue-500 font-medium mr-1">
    <i data-lucide="loader-circle" class="w-3 h-3 animate-spin"></i>
    Syncing...
</span>
@endsection

@section('script')
<script>
    // ─── Toast Mixin ─────────────────────────────────────────────────────────
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

    // ─── Background Sync on Page Load ────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        triggerBackgroundSync();
    });

    function triggerBackgroundSync() {
        const segment = "{{ $segment }}";
        const folder  = "{{ $folder }}";
        const badge   = document.getElementById('syncStatusBadge');

        if (badge) badge.classList.remove('hidden');

        fetch("{{ route('nse.sync.background', ['segment' => ':seg']) }}".replace(':seg', segment), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ folder: folder })
        })
        .then(res => res.json())
        .then(data => {
            if (badge) badge.classList.add('hidden');
            if (data.status === 'ok') {
                refreshFolderTable(segment, folder, data.lastSynced);
            }
            // 'in_progress' → silently skip
        })
        .catch(() => {
            if (badge) badge.classList.add('hidden');
        });
    }

    // ─── Refresh Folder Table via AJAX ───────────────────────────────────────
    function refreshFolderTable(segment, folder, lastSynced) {
        const url = "{{ route('nse.folder.contents.ajax', ['segment' => ':seg']) }}"
            .replace(':seg', segment) + '?folder=' + encodeURIComponent(folder);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                // Step 1: Inject fresh rows
                document.getElementById('folderTableBody').innerHTML = data.html;

                // Step 2: Reinit using the master layout's shared function
                // ✅ This is the only DataTables init — no double-init conflict
                if (typeof window.initActivityTable === 'function') {
                    window.initActivityTable();
                }

                // Step 3: Reinit Lucide icons
                lucide.createIcons();

                // Step 4: Update last synced text
                if (lastSynced) {
                    const syncedEl = document.querySelector('[data-last-synced]');
                    if (syncedEl) syncedEl.textContent = lastSynced;
                }
            }
        })
        .catch(() => {
            // Silent fail — cached data remains visible
        });
    }

    // ─── Manual Sync Now Button ───────────────────────────────────────────────
    function syncNow(segment, folder) {
        const btn = document.querySelector('.btn-sync');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> SYNCING...';
        lucide.createIcons();

        fetch("{{ route('nse.sync.background', ['segment' => ':seg']) }}".replace(':seg', segment), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ folder: folder })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            lucide.createIcons();

            if (data.status === 'ok') {
                Toast.fire({ icon: 'success', title: 'Sync completed. Refreshing...' });
                refreshFolderTable(segment, folder, data.lastSynced);
            } else if (data.status === 'in_progress') {
                Toast.fire({ icon: 'info', title: 'Sync already in progress.' });
            } else {
                Toast.fire({ icon: 'error', title: 'Sync failed. Please retry.' });
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            lucide.createIcons();
            Toast.fire({ icon: 'error', title: 'Something went wrong.' });
        });
    }

    // ─── File Download ────────────────────────────────────────────────────────
    function triggerDownload(btn, id) {
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-circle" class="w-4 h-4 animate-spin mr-2"></i>`;
        lucide.createIcons();

        const url = "{{ route('nse.file.prepare', ['id' => ':id']) }}"
            .replace(':id', id) + '?source=today';

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Downloading...' });
                btn.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 text-success"></i>&nbsp;Downloaded`;
                lucide.createIcons();
                window.location.href = data.url;
            } else {
                throw new Error('Download failed.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Toast.fire({
                icon: 'error',
                title: 'Download Failed',
                text: 'Please retry after some time.',
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: false
            });
            btn.innerHTML = `<i data-lucide="x" class="w-4 h-4 mr-2"></i>`;
            lucide.createIcons();
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }, 3000);
        });
    }

    // ─── Bulk Selection ───────────────────────────────────────────────────────
    function checkSelection() {
        const count = document.querySelectorAll('.row-selector:checked').length;
        const bar   = document.getElementById('bulkActionBar');
        document.getElementById('selectedCount').innerText = count;

        if (count > 0) {
            bar.classList.remove('translate-y-[150%]', 'opacity-0');
        } else {
            bar.classList.add('translate-y-[150%]', 'opacity-0');
        }
    }

    function toggleAll(masterCheckbox) {
        document.querySelectorAll('.row-selector').forEach(cb => cb.checked = masterCheckbox.checked);
        checkSelection();
    }

    function clearSelection() {
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        checkSelection();
    }

    // ─── Bulk Download ────────────────────────────────────────────────────────
    function downloadSelected() {
        const selectedCheckboxes = document.querySelectorAll('.row-selector:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

        if (!selectedIds.length) {
            Toast.fire({ icon: 'warning', title: 'No files selected' });
            return;
        }

        const btn = document.querySelector('.btn-bulk-action');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Zipping...';
        lucide.createIcons();

        fetch("{{ route('nse.member.download.bulk.prepare') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.message || 'Server Error'); });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.href = data.url;

                selectedCheckboxes.forEach(cb => {
                    const row = cb.closest('tr');
                    if (row) {
                        const rowDownloadBtn = row.querySelector('button[onclick*="triggerDownload"]');
                        if (rowDownloadBtn) {
                            rowDownloadBtn.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 text-success"></i>&nbsp;Downloaded`;
                            rowDownloadBtn.style.pointerEvents = 'none';
                        }
                    }
                });
                lucide.createIcons();

                Toast.fire({ icon: 'success', title: 'Download started!' });

                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    lucide.createIcons();
                    clearSelection();
                }, 2000);

            } else {
                throw new Error('Download failed.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Toast.fire({
                icon: 'error',
                title: 'Download Failed',
                text: 'Please retry after some time.',
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: false
            });
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            lucide.createIcons();
        });
    }
</script>
@endsection