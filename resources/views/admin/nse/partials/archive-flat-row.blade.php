@php
    $meta = $node['_meta'] ?? null;
    if (!$meta || strtolower($meta->type) === 'folder') return;

    $createdAt = \Carbon\Carbon::parse($meta->created_at);
    $updatedAt = \Carbon\Carbon::parse($meta->nse_modified_at);
@endphp

<div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 hover:bg-gray-50 transition">

    <!-- Left -->
    <div class="flex items-center gap-4 flex-1">

        <input type="checkbox"
            class="row-selector rounded border-gray-300 text-indigo-600"
            value="{{ $meta->id }}"
            onchange="checkSelection()">

        <div class="text-sm font-medium text-gray-700">
            {{ $meta->name }}
        </div>
    </div>

    <!-- Created -->
    <div class="text-sm text-gray-500 w-32 text-right">
        {{ $createdAt->format('d M H:i') }}
    </div>

    <!-- Updated -->
    <div class="text-sm text-gray-500 w-32 text-right">
        {{ $updatedAt->format('d M H:i') }}
    </div>

    <!-- Download -->
    <div class="w-28 text-right">
        <button onclick="triggerDownload(this, {{ $meta->id }})"
            class="px-4 py-1 text-xs font-semibold border border-gray-300 rounded-md hover:bg-gray-100 transition">
            DOWNLOAD
        </button>
    </div>

</div>
