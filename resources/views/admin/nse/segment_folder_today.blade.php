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
                <tbody>
                    @forelse($contents as $item)
                    @php
                    $isFolder = $item->type == 'Folder';
                    $url = 'folder=' . $item->parent_folder .'/'. $item->name;
                    $url = str_replace('root/', '', $url);
                    if ($item->nse_created_at && $item->nse_modified_at) {
                    // Check 1: Was it modified after it was created?
                    $afterCreation = $item->nse_modified_at->gt($item->nse_created_at);

                    // Check 2: Was it modified today?
                    $modifiedToday = $item->nse_modified_at->isToday();

                    $isModified = $afterCreation && $modifiedToday;
                    }
                    $currentPath = url()->current();
                    @endphp
                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50">
                        <td class="p-4">
                            @if (!$isFolder)
                            <input type="checkbox" value="{{ $item->id }}" onchange="checkSelection()" class="row-selector w-4 h-4 custom-checkbox rounded border-gray-300">
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-700 font-medium" style="display: none;">
                            {{ $item->type }}
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-gray-900 flex items-center gap-3 folder_col">
                            {{--<a href="{{ ($isFolder) ? $currentPath : '#' }}?{{($isFolder) ? $url : ''}}" class="flex items-center gap-3">--}}
                            <div
                                class="w-8 h-8 flex items-center justify-center {{ $isFolder ? 'bg-indigo-100 rounded-lg' : 'bg-indigo-100 rounded-lg' }}">
                                <i data-lucide="{{ $isFolder ? 'folder' : 'file' }}" class="w-5 h-5 {{ $isFolder ? 'text-yellow-500 fill-yellow-500/20' : 'text-indigo-600' }}"></i>
                            </div>
                            <span class="break-all">{{ $item->name }}</span>
                            {{--</a>--}}
                        </td>
                        <td class="px-6 py-4 text-gray-700 font-medium">
                            {{ $item->nse_created_at ? $item->nse_created_at->setTimezone('Asia/Kolkata')->format('Y-m-d h:i a') : '' }}
                        </td>
                        <td class="px-6 py-4 text-gray-700 font-medium">
                            <div class="flex flex-col">
                                <span>{{ $item->nse_modified_at ? $item->nse_modified_at->setTimezone('Asia/Kolkata')->format('Y-m-d h:i a') : '' }}</span>
                                @if ($isModified)
                                    <span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                        Modified
                                    </span>
                                @else
                                    @if($modifiedToday)
                                        <span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                                            <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                            New
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-left">
                            <div class="flex items-center gap-3">
                                @if (!$isFolder)
                                {{-- Download Button --}}
                                <button onclick="triggerDownload(this, {{ $item->id }})"
                                    class="inline-flex items-center font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors download_open">
                                    <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                    Download
                                </button>
                                @else
                                {{-- Open Folder Link --}}
                                <a href="{{ $currentPath }}?{{ $url }}"
                                    class="inline-flex items-center font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors download_open">
                                    <i data-lucide="folder-open" class="w-4 h-4 mr-2"></i>
                                    Open
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-16 text-gray-500">
                            <i data-lucide="cloud-off" class="w-12 h-12 mx-auto text-gray-300"></i>
                            <p class="mt-4 text-lg font-semibold text-gray-600">No activity found.</p>
                            <p class="text-sm">Sync to fetch the latest files.</p>
                        </td>
                    </tr>
                    @endforelse
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
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    function triggerDownload(btn, id) {
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML =
            `<i data-lucide="loader-circle" class="w-4 h-4 animate-spin mr-2"></i>`;
        lucide.createIcons();

        const url = "{{ route('nse.file.prepare', ['id' => ':id']) }}".replace(':id', id) + '?source=today';

        fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
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

                    // setTimeout(() => {
                    //     btn.disabled = false;
                    //     btn.innerHTML = originalContent;
                    // }, 2000);
                } else {
                    throw new Error('Download failed. Please Retry after some time.');
                }
            })
            .catch(error => {
                console.error('Error:', error);

                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed',
                    text: 'Please Retry after some time.',
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

    function syncNow(segment, folder) {
        window.location.reload();
        return;
        const lastSynced = new Date();
        const target = new Date(lastSynced.getTime() + 30 * 60 * 1000);

        const timer = setInterval(() => {

            const now = new Date().getTime();
            const distance = target - now;

            if (distance <= 0) {
                clearInterval(timer);
                document.getElementById("countdown").innerHTML = "";
                return;
            }

            const minutes = Math.floor(distance / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("countdown").innerHTML = "Sync Countdown Timer: " +
                minutes.toString().padStart(2, '0') + ":" +
                seconds.toString().padStart(2, '0');

        }, 1000);
        const btn = document.querySelector('.btn-sync');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML =
            '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> REFRESHING...';
        lucide.createIcons();

        const url = "{{ route('nse.sync.clear', ['segment' => ':segment', 'folder' => ':folder']) }}".replace(
            ':segment', segment).replace(':folder', folder);

        fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('syncProgressWrapper').classList.remove('hidden');

                    Toast.fire({
                        icon: 'info',
                        title: 'Sync started...'
                    });

                    startProgressPolling(segment);
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: 'Failed to clear cache.'
                    });
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Toast.fire({
                    icon: 'error',
                    title: 'Something went wrong.'
                });
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                lucide.createIcons();
            });
    }


    let progressInterval = null;

    function startProgressPolling(segment) {

        if (progressInterval) {
            clearInterval(progressInterval);
        }

        progressInterval = setInterval(() => {

            fetch("{{ route('nse.sync.progress', ['segment' => ':segment']) }}"
                    .replace(':segment', segment))
                .then(res => res.json())
                .then(data => {

                    const bar = document.getElementById('syncProgressBar');

                    bar.style.width = data.percentage + '%';
                    bar.innerText = data.percentage + '%';

                    if (data.status === 'completed') {

                        clearInterval(progressInterval);

                        Toast.fire({
                            icon: 'success',
                            title: 'Sync Completed'
                        });

                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                });

        }, 2000);
    }

    function checkSelection() {
        const checkboxes = document.querySelectorAll('.row-selector:checked');
        const count = checkboxes.length;
        const bar = document.getElementById('bulkActionBar');

        document.getElementById('selectedCount').innerText = count;

        if (count > 0) {
            bar.classList.remove('translate-y-[150%]', 'opacity-0');
        } else {
            bar.classList.add('translate-y-[150%]', 'opacity-0');
        }
    }

    function toggleAll(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.row-selector');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        checkSelection();
    }

    function clearSelection() {
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        checkSelection();
    }

    function downloadSelected() {
        const selectedCheckboxes = document.querySelectorAll('.row-selector:checked');
        const selectedIds = Array.from(document.querySelectorAll('.row-selector:checked'))
            .map(cb => cb.value);

        if (selectedIds.length === 0) {
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

        fetch("{{ route('nse.member.download.bulk.prepare') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    ids: selectedIds
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || 'Server Error');
                    });
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
                } else {
                    throw new Error('Download failed. Please Retry after some time.');
                }
            })
            .catch(error => {
                console.error('Error:', error);

                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed',
                    text: 'Please Retry after some time.',
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