@extends('layouts.user_type.auth')

@section('page_title', __('Extranet Sync - ' . Str::upper($segment)))

@php
    $folder = trim($folder ?? 'root', '/');
    $folder = $folder === '' ? 'root' : $folder;
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
            <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_4px_rgba(34,197,94,0.6)]"></span>
        @else
            <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
        @endif
        <span>Last synced:
            @if($lastSynced)
                {{ \Carbon\Carbon::parse($lastSynced)->format('h:i a') }}
            @endif
        </span>
    </div>
</div>
@endsection

@section('content')
<main class="flex-1 p-6 bg-gray-50">
    <nav class="p-2 text-sm font-medium text-gray-600">
        <ol class="flex items-center gap-2 flex-wrap">
            <li>NSE Common Segment</li>
            <li class="text-gray-400">/</li>
            <li>
                <a href="{{ route('nse.common.segment.folder.today', ['segment' => $segment, 'folder' => 'root']) }}"
                    class="hover:text-brand font-semibold">{{ Str::upper($segment) }}</a>
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
                        class="hover:text-brand font-semibold">{{ $part }}</a>
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="bg-white rounded-lg shadow-lg">
        <div class="px-6 py-3 border-b border-gray-200">
            <div class="flex items-center justify-between flex-wrap gap-2">

                <div class="flex items-center gap-3 text-lg font-bold text-gray-900">
                    <i data-lucide="sun" class="w-6 h-6 text-amber-500"></i>
                    All Activity
                </div>

                {{-- Syncing badge --}}
                <div id="syncStatusBadge" style="display:none;"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-200 bg-indigo-50">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-indigo-500"></span>
                    </span>
                    <span class="text-xs font-semibold text-indigo-600 whitespace-nowrap">Syncing</span>
                    <span class="flex gap-0.5 items-center" id="syncDots">
                        <span class="w-1 h-1 rounded-full bg-indigo-400 animate-bounce" style="animation-delay:0ms"></span>
                        <span class="w-1 h-1 rounded-full bg-indigo-400 animate-bounce" style="animation-delay:150ms"></span>
                        <span class="w-1 h-1 rounded-full bg-indigo-400 animate-bounce" style="animation-delay:300ms"></span>
                    </span>
                </div>

                {{-- Done badge --}}
                <div id="syncDoneBadge" style="display:none;"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-green-200 bg-green-50">
                    <span class="relative inline-flex h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                    <span class="text-xs font-semibold text-green-600 whitespace-nowrap">Updated just now</span>
                </div>
            </div>
        </div>

        <div class="relative overflow-x-auto">
            {{--
                COLUMN COUNT = 5:
                0 = Checkbox
                1 = Folder / File Name
                2 = Created
                3 = Last Updated
                4 = Action
                ✅ Hidden Type <th> REMOVED to match <td> count in rows
            --}}
            <table class="text-sm NseSegmentTable text-left" id="activityTable">
                <thead class="text-xs text-gray-700 font-bold uppercase bg-gray-100 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 w-12">
                            <input type="checkbox" onchange="toggleAll(this)"
                                class="w-4 h-4 custom-checkbox rounded border-gray-300">
                        </th>
                        <th scope="col" class="px-6 py-3" style="display: none;">Type</th>
                        <th class="px-6 py-3 folder_col">Folder / File Name</th>
                        <th class="px-6 py-3 CreatedDate">Created</th>
                        <th class="px-6 py-3 LastUpdate">Last Updated</th>
                        <th class="px-6 py-3 text-right action_col">Action</th>
                    </tr>
                </thead>
                <tbody id="folderTableBody">
                </tbody>
            </table>
        </div>
    </div>
</main>

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

@section('script')
<script>
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false,
        timer: 3000, timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    let dataTable = null;
    let syncTriggered = false;

    function initServerSideDataTable() {
        if ($.fn.DataTable.isDataTable('#activityTable')) {
            $('#activityTable').DataTable().destroy();
        }

        var table = $('#activityTable').DataTable({
            "serverSide": true,
            "processing": true,
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
            "ajax": {
                "url": "{{ route('nse.common.datatable', ['segment' => $segment, 'folder' => $folder]) }}",
                "type": "GET",
                "dataSrc": function(json) {
                    if (json.data) {
                        return json.data;
                    }
                    return [];
                },
                "error": function(xhr, error, thrown) {
                    console.log('DataTables AJAX Error:', error);
                }
            },
            "columns": [
                { "data": "checkbox", "name": "checkbox", "orderable": false, "searchable": false },
                { "data": "type", "name": "type", "visible": false },
                { "data": "name", "name": "name", "orderable": true, "searchable": true },
                { "data": "nse_created_at", "name": "nse_created_at", "orderable": true, "searchable": false },
                { "data": "nse_modified_at", "name": "nse_modified_at", "orderable": true, "searchable": false },
                { "data": "action", "name": "action", "orderable": false, "searchable": false }
            ],
            "order": [[1, "desc"], [4, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [0, 5] },
                { "visible": false, "targets": [1] }
            ],
            "language": {
                "processing": '<div class="flex items-center justify-center py-4"><i data-lucide="loader-circle" class="w-6 h-6 animate-spin text-brand"></i><span class="ml-2">Loading...</span></div>',
                "search": "Search files:",
                "emptyTable": "No activity found. Sync to fetch the latest files.",
                "paginate": {
                    "previous": '<i class="fa fa-angle-left"></i>',
                    "next": '<i class="fa fa-angle-right"></i>'
                },
                "lengthMenu": "Show _MENU_ entries"
            },
            "drawCallback": function(settings) {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            },
            "initComplete": function(settings, json) {
                if (typeof lucide !== 'undefined') lucide.createIcons();
                if (!syncTriggered) {
                    syncTriggered = true;
                    setTimeout(function() {
                        triggerBackgroundSync();
                    }, 500);
                }
            }
        });

        dataTable = table;
        return table;
    }

    document.addEventListener('DOMContentLoaded', function() {
        initServerSideDataTable();
    });

    function triggerBackgroundSync() {
        const segment   = "{{ $segment }}";
        const folder    = "{{ $folder }}";
        const badge     = document.getElementById('syncStatusBadge');
        const doneBadge = document.getElementById('syncDoneBadge');

        if (badge)     badge.style.display = 'inline-flex';
        if (doneBadge) doneBadge.style.display = 'none';

        fetch("{{ route('nse.common.sync.background', ['segment' => ':seg']) }}".replace(':seg', segment), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ folder: folder })
        })
        .then(res => res.json())
        .then(data => {
            if (badge) badge.style.display = 'none';
            if (data.status === 'ok') {
                reloadDataTable();
                if (doneBadge) {
                    doneBadge.style.display = 'inline-flex';
                    setTimeout(() => { doneBadge.style.display = 'none'; }, 4000);
                }
            }
        })
        .catch(() => {
            if (badge) badge.style.display = 'none';
        });
    }

    function reloadDataTable() {
        if (dataTable) {
            dataTable.ajax.reload(null, false);
        }
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
            body: JSON.stringify({ folder: folder })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            lucide.createIcons();

            if (data.status === 'ok') {
                Toast.fire({ icon: 'success', title: 'Sync completed. Refreshing...' });
                reloadDataTable();
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

    function triggerDownload(btn, id) {
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-circle" class="w-4 h-4 animate-spin mr-2"></i>`;
        lucide.createIcons();

        const url = "{{ route('nse.common.file.prepare', ['id' => ':id']) }}".replace(':id', id);

        fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(response => { if (!response.ok) throw new Error(); return response.json(); })
        .then(data => {
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Downloading...' });
                btn.innerHTML = `<i data-lucide="check-circle" class="w-5 h-5 text-success"></i>&nbsp;Downloaded`;
                lucide.createIcons();
                window.location.href = data.url;
            } else throw new Error();
        })
        .catch(() => {
            Toast.fire({ icon: 'error', title: 'Download Failed.', text: 'Please retry after some time.', timer: 5000 });
            btn.innerHTML = `<i data-lucide="x" class="w-4 h-4 mr-2"></i>`;
            lucide.createIcons();
            setTimeout(() => { btn.disabled = false; btn.innerHTML = originalContent; }, 3000);
        });
    }

    function checkSelection() {
        const count = document.querySelectorAll('.row-selector:checked').length;
        const bar   = document.getElementById('bulkActionBar');
        document.getElementById('selectedCount').innerText = count;
        count > 0
            ? bar.classList.remove('translate-y-[150%]', 'opacity-0')
            : bar.classList.add('translate-y-[150%]', 'opacity-0');
    }

    function toggleAll(masterCheckbox) {
        document.querySelectorAll('.row-selector').forEach(cb => cb.checked = masterCheckbox.checked);
        checkSelection();
    }

    function clearSelection() {
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        checkSelection();
    }

    function downloadSelected() {
        const selectedCheckboxes = document.querySelectorAll('.row-selector:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

        if (!selectedIds.length) { Toast.fire({ icon: 'warning', title: 'No files selected' }); return; }

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
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => {
            if (!response.ok) return response.json().then(err => { throw new Error(err.message); });
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.href = data.url;
                Toast.fire({ icon: 'success', title: 'Download started!' });
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    lucide.createIcons();
                    clearSelection();
                }, 2000);
            } else throw new Error();
        })
        .catch(() => {
            Toast.fire({ icon: 'error', title: 'Download Failed', text: 'Please retry after some time.', timer: 5000 });
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            lucide.createIcons();
        });
    }
</script>
@endsection