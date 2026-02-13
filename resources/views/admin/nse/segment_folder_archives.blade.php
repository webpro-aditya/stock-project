@extends('layouts.user_type.auth')

@section('page_title', 'NSE Archives')

@section('style')

@endsection

@section('header-actions')
    <div class="text-right">
        <button onclick="syncNow('{{ $segment }}', '{{ $folder }}')"
            class="btn-sync flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-hover px-4 py-2 rounded-lg transition-colors">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            SYNC NOW
        </button>
        <div class="text-xs text-gray-500 mt-1">Archives</div>
    </div>
@endsection

@section('content')
    <div class="bg-white rounded-lg shadow-sm flex flex-col" style="height: calc(100vh - 110px);">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <i data-lucide="clock" class="w-5 h-5 text-gray-400"></i>
                <h2 class="text-lg font-bold text-gray-800">Archive History</h2>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            <div x-data="{ open: '{{ 'archive-' . ($groupedContents->keys()->first() ? \Carbon\Carbon::parse($groupedContents->keys()->first())->timestamp : '') }}' }" class="space-y-2">
                @forelse($groupedContents as $date => $files)
                    @php
                        $carbonDate = \Carbon\Carbon::parse($date);
                        $day = $carbonDate->format('d');
                        $fullDate = $carbonDate->format('d M Y');
                        $collapseId = 'archive-' . $carbonDate->timestamp;
                    @endphp
                    <div>
                        <button @click="open = open === '{{ $collapseId }}' ? '' : '{{ $collapseId }}'"
                            class="w-full flex items-center justify-between py-3 text-left transition-colors">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-9 h-9 flex-shrink-0 bg-gray-100 text-gray-500 text-xs font-bold flex items-center justify-center rounded-full">
                                    {{ $day }}
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-gray-700">{{ $fullDate }}</p>
                                    <p class="text-xs text-gray-400">{{ $files->count() }} Files generated</p>
                                </div>
                            </div>
                            <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400 transition-transform"
                                x-bind:class="{ 'rotate-180': open === '{{ $collapseId }}' }"></i>
                        </button>
                        <div x-show="open === '{{ $collapseId }}'" x-collapse>
                            <div class="overflow-x-auto pt-2">
                                <table class="w-full text-left text-sm text-gray-600">
                                    <thead class="text-xs text-gray-400 font-medium">
                                        <tr>
                                            <th class="py-2 px-4 w-10">
                                                <input type="checkbox" onchange="toggleAll(this)" class="rounded border-gray-300 text-brand focus:ring-brand-focus">
                                            </th>
                                            <th class="py-2 px-4">Directory Name</th>
                                            <th class="py-2 px-4">Created</th>
                                            <th class="py-2 px-4">Last updated</th>
                                            <th class="py-2 px-4 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($files as $file)
                                            @php
                                                $createdAt = \Carbon\Carbon::parse($file->created_at);
                                                $updatedAt = \Carbon\Carbon::parse($file->nse_modified_at);
                                                $isModified = !$createdAt->eq($updatedAt);
                                            @endphp
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="py-2 px-4">
                                                    <input type="checkbox" name="selected_files[]" value="{{ $file->id }}" onchange="checkSelection()" class="row-selector rounded border-gray-300 text-brand focus:ring-brand-focus">
                                                </td>
                                                <td class="py-2 px-4 font-medium text-gray-700">{{ $file->name }}</td>
                                                <td class="py-2 px-4 text-gray-500">{{ $createdAt->format('d M H:i') }}</td>
                                                <td class="py-2 px-4 text-gray-500">
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ $updatedAt->format('d M H:i') }}</span>
                                                        @if($isModified)
                                                            <div class="text-yellow-600 bg-yellow-50 rounded-full px-2 py-0.5 text-xs flex items-center gap-1">
                                                                <i data-lucide="clock" class="w-3 h-3"></i> Modified
                                                            </div>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="py-2 px-4 text-center">
                                                    <button onclick="triggerDownload(this, {{ $file->id }})"
                                                        class="inline-flex items-center px-3 py-1 border border-gray-200 text-xs font-semibold text-gray-500 bg-white rounded-md hover:bg-gray-50 hover:text-brand-dark transition-all">
                                                        Download
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16 text-gray-500">
                        <i data-lucide="archive-x" class="w-12 h-12 mx-auto mb-4 text-gray-300"></i>
                        <h3 class="text-lg font-semibold text-gray-600">No Archive History</h3>
                        <p class="text-sm">There are no archived files for this segment yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="text-center py-4 border-t border-gray-100">
            <a href="{{ route('nse.segment.folder.today', ['segment' => $segment, 'folder' => $folder]) }}"
                class="inline-flex flex-col items-center gap-2 text-gray-500 hover:text-brand transition-colors group">
                <span class="font-bold text-sm tracking-wider uppercase">Back to Today's Activity</span>
                <div
                    class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-200 group-hover:bg-brand-light shadow-sm border border-gray-200 transition-colors">
                    <i data-lucide="arrow-up" class="w-5 h-5"></i>
                </div>
            </a>
        </div>
    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
            const originalText = btn.innerText;

            btn.disabled = true;
            btn.innerHTML = '<i class="animate-spin" data-lucide="loader-2"></i>';
            lucide.createIcons(); // Re-render icons

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

                        btn.innerHTML = '<i data-lucide="check"></i>';
                        lucide.createIcons();

                        window.location.href = data.url;

                        setTimeout(() => {
                            btn.disabled = false;
                            btn.innerText = originalText;
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

                    btn.innerHTML = '<i data-lucide="x"></i>';
                    lucide.createIcons();

                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerText = originalText;
                    }, 3000);
                });
        }

        function syncNow(segment, folder) {
            const btn = document.querySelector('.btn-sync');
            const originalHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="animate-spin" data-lucide="loader-2"></i> REFRESHING...';
            lucide.createIcons();

            const url = "{{ route('nse.archive.sync.clear', ['segment' => ':segment', 'folder' => ':folder']) }}".replace(':segment', segment).replace(':folder', folder);

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
                    window.location.reload();
                } else {
                    alert('Failed to clear cache.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Something went wrong.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                lucide.createIcons();
            });
        }

        function checkSelection() {
        const checkboxes = document.querySelectorAll('.row-selector');
        const selectedCount = document.getElementById('selectedCount');
        const bar = document.getElementById('bulkActionBar');

        const count = Array.from(checkboxes).filter(cb => cb.checked).length;
        
        if (selectedCount) {
            selectedCount.innerText = count;
        }

        if (count > 0) {
            bar.classList.remove('translate-y-[150%]', 'opacity-0');
        } else {
            bar.classList.add('translate-y-[150%]', 'opacity-0');
        }
    }

    function toggleAll(masterCheckbox) {
        const accordionItem = masterCheckbox.closest('.divide-y');
        const checkboxes = accordionItem.querySelectorAll('.row-selector');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        checkSelection();
    }

    function clearSelection() {
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
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

        const btn = document.querySelector('.btn-bulk-action');
        const originalHtml = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Zipping...';
        lucide.createIcons(); 

        fetch("{{ route('nse.download.bulk.prepare') }}", {
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
