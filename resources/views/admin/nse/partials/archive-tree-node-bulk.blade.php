@php
    $meta     = $node['_meta'] ?? null;
    $children = $node['children'] ?? [];
    $isFolder = $meta ? strtolower($meta->type) === 'folder' : true;
    $depth    = $depth ?? 0;
@endphp

@if($isFolder)

    {{-- Folder row --}}
    <div x-data="{ open: true }">
        <button @click="open = !open"
            class="w-full flex items-center gap-3 px-6 py-2.5 hover:bg-gray-50 transition-colors text-left"
            style="padding-left: {{ 24 + $depth * 20 }}px">
            <i data-lucide="folder" class="w-4 h-4 text-amber-400 shrink-0"></i>
            <span class="text-sm font-medium text-gray-700 flex-1">{{ $name }}</span>
            <i data-lucide="chevron-down"
               class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200"
               x-bind:class="{ 'rotate-180': open }"></i>
        </button>

        @if(!empty($children))
            <div x-show="open" x-collapse>
                @foreach($children as $childName => $childNode)
                    @include('admin.nse.partials.archive-tree-node-bulk', [
                        'name'  => $childName,
                        'node'  => $childNode,
                        'depth' => $depth + 1
                    ])
                @endforeach
            </div>
        @endif
    </div>

@else

    {{-- File row — matches screenshot exactly --}}
    <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 transition-colors border-t border-gray-50 first:border-t-0"
         style="padding-left: {{ 24 + $depth * 20 }}px">

        {{-- Checkbox --}}
        <input type="checkbox"
            class="row-selector h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand"
            value="{{ $meta->id }}"
            onchange="checkSelection()">

        {{-- Filename --}}
        <span class="flex-1 text-sm text-gray-700 truncate">
            {{ $name }}
        </span>

        {{-- Modified at --}}
        <span class="hidden md:block text-xs text-gray-400 w-32 text-center">
            {{ optional($meta->nse_modified_at)->format('d M H:i') }}
        </span>

        {{-- NSE time (created_at or nse_time) --}}
        <span class="hidden md:block text-xs text-gray-400 w-32 text-center">
            {{ optional($meta->created_at)->format('d M H:i') }}
        </span>

        {{-- Download button --}}
        <button onclick="triggerDownload(this, {{ $meta->id }})"
            class="shrink-0 text-xs font-semibold text-gray-700 border border-gray-300 hover:border-gray-400 hover:bg-gray-50 px-3 py-1.5 rounded transition-colors tracking-wide uppercase">
            Download
        </button>

    </div>

@endif
