@extends('layouts.user_type.auth')

@section('page_title', 'NSE Archives')

@section('header-title')
<span>NSE Member Segment</span>
@endsection
@section('header-actions')
<div class="text-right">
    <button onclick="syncNow('{{ $segment }}')"
        class="btn-sync inline-flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-lg transition-colors">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
        SYNC NOW
    </button>
    @if ($lastSynced && $lastSynced != 'Never')
        <div class="text-xs text-gray-500 mt-1">Last synced: {{ $lastSynced }}</div>
    @endif
</div>
@endsection

@section('content')
<nav class="py-3 mt-4 text-sm font-medium text-gray-600 mx-4">
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

{{-- Card Header --}}
<div class="px-6 py-4 border-b border-gray-100 shadow-sm bg-white mx-4">
    <div class="flex items-center gap-2">
        <i data-lucide="history" class="w-5 h-5 text-gray-400"></i>
        <h2 class="text-base font-bold text-gray-800">Archive History</h2>
    </div>
</div>

{{-- Scrollable Body --}}
<div class="flex-1 overflow-y-auto divide-y divide-gray-100 shadow-sm bg-white mx-4">

    @forelse($treeByDate as $date => $tree)
    @php
    $carbon = \Carbon\Carbon::parse($date);

    // Count only leaf (file) nodes recursively
    $countFiles = function($nodes) use (&$countFiles) {
    $count = 0;
    foreach ($nodes as $node) {
    $meta = $node['_meta'] ?? null;
    $children = $node['children'] ?? [];
    if ($meta && strtolower($meta->type) !== 'folder') {
    $count++;
    }
    $count += $countFiles($children);
    }
    return $count;
    };
    $fileCount = $countFiles($tree);
    @endphp

    <div x-data="{ open: false }" class="group">

        {{-- Date Accordion Header --}}
        <button @click="open = !open"
            class="w-full flex items-center justify-between px-6 py-3 hover:bg-gray-100 transition-colors">

            <div class="flex items-center gap-4">
                {{-- Day circle --}}
                <div class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700 shrink-0">
                    {{ $carbon->format('d') }}
                </div>

                <div class="text-left">
                    <div class="text-sm font-semibold text-gray-800">
                        {{ $carbon->format('d M Y') }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        {{ $fileCount }} {{ Str::plural('File', $fileCount) }} generated
                    </div>
                </div>
            </div>

            <i data-lucide="chevron-down"
                class="w-4 h-4 text-gray-400 transition-transform duration-200"
                x-bind:class="{ 'rotate-180': open }"></i>
        </button>

        {{-- Accordion Body --}}
        <div x-show="open" x-collapse>
            <div class="divide-y divide-gray-50">
                @foreach($tree as $name => $node)
                @include('admin.nse.partials.archive-tree-node-bulk', [
                'name' => $name,
                'node' => $node,
                'depth' => 0
                ])
                @endforeach
            </div>
        </div>

    </div>

    @empty
    <div class="flex flex-col items-center justify-center py-20 text-gray-400">
        <i data-lucide="archive-x" class="w-12 h-12 mb-4 text-gray-300"></i>
        <h3 class="text-base font-semibold text-gray-500">No Archive History</h3>
        <p class="text-sm mt-1 text-gray-400">No past files found for this segment.</p>
    </div>
    @endforelse

</div>

<div class="text-center py-4 border-t border-gray-100">
    <a href="{{ route('nse.segment.folder.today', ['segment' => $segment, 'folder' => 'root']) }}"
        class="inline-flex flex-col items-center gap-2 text-gray-500 hover:text-brand transition-colors group">
        <span class="font-bold text-sm tracking-wider uppercase">Back to Today's Activity</span>
        <div
            class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-200 group-hover:bg-brand-light shadow-sm border border-gray-200 transition-colors">
            <i data-lucide="arrow-up" class="w-5 h-5"></i>
        </div>
    </a>
</div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col" style="height: calc(100vh - 110px);">

    {{-- Bulk Action Bar --}}
    <div id="bulkActionBar"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 flex items-center gap-4 py-2 px-4 pl-5 rounded-full bg-gray-900 shadow-2xl shadow-gray-900/50 border border-gray-800 transition-all duration-300 translate-y-[150%] opacity-0">

        <div class="flex items-center gap-3 text-white">
            <div id="selectedCount"
                class="w-7 h-7 text-xs font-bold flex items-center justify-center bg-brand rounded-full">
                0
            </div>
            <span class="text-sm font-semibold">Items Selected</span>
        </div>

        <button
            class="flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-full transition-colors btn-bulk-action"
            onclick="downloadSelected()">
            <i data-lucide="download-cloud" class="w-4 h-4"></i>
            Download All
        </button>

        <button onclick="clearSelection()"
            class="text-gray-500 hover:text-gray-300 p-1 rounded-full transition-colors">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>

    </div>

    @endsection


    @section('script')
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        function triggerDownload(btn, id) {

            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerHTML = '<i class="animate-spin" data-lucide="loader-2"></i>';
            lucide.createIcons();

            const url = "{{ route('nse.file.prepare', ['id' => ':id']) }}"
                .replace(':id', id) + '?source=archive';

            fetch(url, {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {

                    if (data.success) {
                        window.location.href = data.url;
                    } else {
                        throw new Error(data.message);
                    }

                })
                .catch(error => {
                    Toast.fire({
                        icon: 'error',
                        title: error.message
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerText = originalText;
                });
        }


        function checkSelection() {
            const checkboxes = document.querySelectorAll('.row-selector');
            const selectedCount = document.getElementById('selectedCount');
            const bar = document.getElementById('bulkActionBar');

            const count = Array.from(checkboxes).filter(cb => cb.checked).length;

            selectedCount.innerText = count;

            if (count > 0) {
                bar.classList.remove('translate-y-[150%]', 'opacity-0');
            } else {
                bar.classList.add('translate-y-[150%]', 'opacity-0');
            }
        }

        function clearSelection() {
            document.querySelectorAll('.row-selector').forEach(cb => cb.checked = false);
            checkSelection();
        }

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
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.url;
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    Toast.fire({
                        icon: 'error',
                        title: error.message
                    });
                });
        }
    </script>
    @endsection