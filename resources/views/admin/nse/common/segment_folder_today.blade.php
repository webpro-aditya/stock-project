@extends('layouts.user_type.auth')

@section('page_title', __('NSE Explorer - ' . Str::upper($segment)))

@php
    $folder = trim($folder ?? '', '/');
    $parts = $folder ? explode('/', $folder) : [];
    $path = '';
@endphp

@section('header-title')
<span>NSE Common Segment</span>
@endsection

@section('header-actions')
<div class="text-right">
    <button onclick="syncNow('{{ $segment }}', '{{ $folder }}')"
        class="btn-sync flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-3 py-2 rounded-lg transition-colors">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        SYNC NOW
    </button>
    @if ($lastSynced && $lastSynced != 'Never')
        <div class="text-xs text-gray-500 mt-1">Last synced: {{ $lastSynced }}</div>
    @endif
</div>
@endsection

@section('content')
<main class="flex-1 p-6 bg-gray-50">
    <div class="bg-white rounded-lg shadow-sm">
        <div class="px-6 py-3 border-b border-gray-200">
            <div class="flex items-center gap-3 text-lg font-bold text-gray-900">
                <i data-lucide="sun" class="w-6 h-6 text-amber-500"></i>
                Today's Activity
            </div>
        </div>

        <nav class="p-2 text-sm font-medium text-gray-600">
            <ol class="flex items-center gap-2 flex-wrap">
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
        <div class="relative overflow-x-auto" style="max-height: 60vh;">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 font-bold uppercase bg-gray-100 sticky top-0">
                    <tr>
                        <th scope="col" class="px-4 py-3 w-12">
                            <input type="checkbox" onchange="toggleAll(this)"
                                class="w-4 h-4 custom-checkbox rounded border-gray-300">
                        </th>
                        <th scope="col" class="px-6 py-3">File Name</th>
                        <th scope="col" class="px-6 py-3">Created</th>
                        <th scope="col" class="px-6 py-3">Last Updated</th>
                        <th scope="col" class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contents as $item)
                    @php
                    $isFolder = $item->type == 'Folder';
                    $url = 'folder=' . $item->parent_folder .'/'. $item->name;
                    $url = str_replace('root/', '', $url);
                    $isModified = $item->created_at->ne($item->nse_modified_at);
                    $currentPath = url()->current();
                    @endphp
                    <tr class="bg-white border-b border-gray-200 hover:bg-gray-50">
                        <td class="p-4">
                            <input type="checkbox" value="{{ $item->id }}" onchange="checkSelection()" class="row-selector w-4 h-4 custom-checkbox rounded border-gray-300"
                                @if ($isFolder) disabled @endif>
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                            <a href="{{ $currentPath }}?{{$url}}" class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 flex items-center justify-center {{ $isFolder ? 'bg-indigo-100 rounded-lg' : 'bg-indigo-100 rounded-lg' }}">
                                    <i data-lucide="{{ $isFolder ? 'folder' : 'file' }}" class="w-5 h-5 {{ $isFolder ? 'text-yellow-500 fill-yellow-500/20' : 'text-indigo-600' }}"></i>
                                </div>
                                {{ $item->name }}
                            </a>
                        </td>
                        <td class="px-6 py-4 text-gray-700 font-medium">
                            {{ $item->created_at ? $item->created_at->format('d M H:i') : '' }}
                        </td>
                        <td class="px-6 py-4 text-gray-700 font-medium">
                            <div class="flex flex-col">
                                <span>{{ $item->nse_modified_at ? $item->nse_modified_at->format('d M H:i') : '' }}</span>
                                @if ($isModified)
                                <span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                                    <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                    Modified
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if (!$isFolder)
                            <button onclick="triggerDownload(this, {{ $item->id }})"
                                class="font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors">Download</button>
                            @else
                            <a href="{{ $currentPath }}?{{$url}}"
                                class="font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors">Open</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-16 text-gray-500">
                            <i data-lucide="cloud-off" class="w-12 h-12 mx-auto text-gray-300"></i>
                            <p class="mt-4 text-lg font-semibold text-gray-600">No activity found for today.</p>
                            <p class="text-sm">Check back later or sync to fetch the latest files.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
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

        const url = "{{ route('nse.file.prepare', ['id' => ':id']) }}".replace(':id', id);

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

                    btn.innerHTML = `<i data-lucide="check" class="w-4 h-4 mr-2"></i>`;
                    lucide.createIcons();

                    window.location.href = data.url;

                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Download failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);

                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed',
                    text: error.message
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
        const btn = document.querySelector('.btn-sync');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML =
            '<i data-lucide="loader-circle" class="w-4 h-4 animate-spin"></i> REFRESHING...';
        lucide.createIcons();

        const url = "{{ route('nse.common.sync.clear', ['segment' => ':segment', 'folder' => ':folder']) }}".replace(
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
                debugger;
                if (data.success) {
                    Toast.fire({
                        icon: 'info',
                        title: 'Refreshing the page...'
                    });
                    setTimeout(() => window.location.reload(), 1000);
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

    function updateTime() {
        const timeElement = document.getElementById('current-time');
        if (timeElement) {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            timeElement.innerText = `${hours}:${minutes}:${seconds}`;
        }
    }

    setInterval(updateTime, 1000);
    updateTime();

    function downloadSelected() {
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

        fetch("{{ route('nse.common.download.bulk.prepare') }}", {
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

                    btn.innerHTML = '<i data-lucide="check" class="w-4 h-4 mr-2"></i>';
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
                    throw new Error(data.message || 'Download preparation failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);

                Toast.fire({
                    icon: 'error',
                    title: 'Download Failed',
                    text: error.message
                });

                btn.disabled = false;
                btn.innerHTML = originalHtml;
                lucide.createIcons();
            });
    }
</script>
@endsection