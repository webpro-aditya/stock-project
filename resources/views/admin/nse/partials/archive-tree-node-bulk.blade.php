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
            class="w-full flex items-center gap-3 px-6 py-2.5 hover:bg-gray-50 transition-colors text-left border-b border-gray-50"
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
    {{-- File row — Matches Screenshot --}}
    <div class="flex items-center gap-4 px-6 py-3 hover:bg-blue-50/30 transition-colors border-b border-gray-50 last:border-b-0"
         style="padding-left: {{ 24 + $depth * 20 }}px">

        {{-- Checkbox --}}
        <input type="checkbox"
            class="row-selector h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand"
            value="{{ $meta->id }}"
            onchange="checkSelection()">

        {{-- Filename --}}
        <div class="flex-1 min-w-0">
            <span class="text-sm text-gray-700 font-medium truncate block">
                {{ $name }}
            </span>
        </div>

        {{-- Modified at --}}
        <div class="hidden md:block w-32 text-center">
            <span class="text-xs text-gray-500 font-medium">
                {{ optional($meta->nse_modified_at)->format('d M H:i') }}
            </span>
        </div>

        {{-- NSE time --}}
        <div class="hidden md:block w-32 text-center">
            <span class="text-xs text-gray-500 font-medium">
                {{ optional($meta->created_at)->format('d M H:i') }}
            </span>
        </div>

        {{-- Action --}}
        <div class="w-24 flex justify-center">
            <button onclick="triggerDownload(this, {{ $meta->id }})"
                class="text-[11px] font-bold text-gray-600 border border-gray-300 hover:border-brand hover:text-brand px-3 py-1 rounded transition-all uppercase tracking-tighter">
                Download
            </button>
        </div>
    </div>
@endif