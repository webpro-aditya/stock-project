@php
$meta = $node['_meta'] ?? null;
$children = $node['children'] ?? [];
$isFolder = empty($meta) || strtolower($meta->type) === 'folder';
$depth = $depth ?? 0;

// Detect if this folder has file children
$hasFiles = collect($children)->contains(function ($child) {
return isset($child['_meta']) &&
$child['_meta'] &&
strtolower($child['_meta']->type) !== 'folder';
});
@endphp

@if($isFolder)

<div x-data="{ open: false }" class="group">

    {{-- Folder Row --}}
    <div
        class="flex items-center gap-3 px-6 py-2.5 hover:bg-gray-50 transition-colors cursor-pointer border-b border-gray-50"
        style="padding-left: {{ 24 + ($depth * 20) }}px"
        @click="open = !open">
        <i data-lucide="folder" class="w-4 h-4 text-yellow-500"></i>

        <span class="text-sm font-medium text-gray-700 flex-1">
            {{ $name }}
        </span>

        <i data-lucide="chevron-down"
            class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200"
            x-bind:class="{ 'rotate-180': open }"></i>
    </div>

    {{-- Children --}}
    @if(!empty($children))
    <div x-show="open" x-collapse>

        {{-- Show header ONLY if this folder has files --}}
        @if($hasFiles)
        <div class="flex items-center gap-4 px-6 py-2 text-xs font-semibold uppercase text-gray-500 bg-gray-50 border-y border-gray-100"
            style="padding-left: {{ 44 + ($depth * 20) }}px">

            <div class="w-4"></div> {{-- space for checkbox --}}

            <div class="flex-1">
                File Name
            </div>

            <div class="hidden md:block w-32 text-center">
                Created
            </div>

            <div class="hidden md:block w-32 text-center">
                Modified
            </div>

            <div class="w-24 text-center">
                Action
            </div>
        </div>
        @endif

        @foreach($children as $childName => $childNode)
        @include('admin.nse.partials.archive-tree-node-bulk', [
        'name' => $childName,
        'node' => $childNode,
        'depth' => $depth + 1,
        'archiveDate' => $archiveDate
        ])
        @endforeach

    </div>
    @endif

</div>

@else

{{-- File Row --}}
<div
    class="flex items-center gap-4 px-6 py-3 hover:bg-blue-50/30 transition-colors border-b border-gray-50"
    style="padding-left: {{ 24 + ($depth * 20) }}px"
>

    {{-- Checkbox --}}
    <input type="checkbox"
        class="row-selector h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand"
        value="{{ $meta->id }}"
        onchange="checkSelection()">

    {{-- File Name --}}
    <div class="flex-1 min-w-0">
        <span class="text-sm text-gray-700 font-medium truncate block">
            {{ $name }}
        </span>
    </div>

    {{-- Created At --}}
    <div class="hidden md:block w-32 text-center">
        <span class="text-xs text-gray-500 font-medium">
            {{ optional($meta->nse_created_at)->format('d M Y h:i a') }}
        </span>
    </div>

    {{-- Modified At --}}
    <div class="hidden md:block w-32 text-center">
        <span class="text-xs text-gray-500 font-medium">
            {{ optional($meta->nse_modified_at)->format('d M Y h:i a') }}
        </span>
    </div>

    {{-- Action --}}
    <div class="w-24 flex justify-center">
        <button
            onclick="triggerDownload(this, {{ $meta->id }}, '{{ $archiveDate }}')"
            class="text-[11px] font-bold text-gray-600 border border-gray-300 hover:border-brand hover:text-brand px-3 py-1 rounded transition-all uppercase tracking-tighter">
            Download
        </button>
    </div>

</div>

@endif