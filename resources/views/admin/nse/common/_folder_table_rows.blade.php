@forelse($contents as $item)
@php
    $isFolder = $item->type == 'Folder';
    $url = 'folder=' . $item->parent_folder . '/' . $item->name;
    $url = str_replace('root/', '', $url);

    $isModified = false;
    $modifiedToday = false;

    if ($item->nse_created_at && $item->nse_modified_at) {
    $afterCreation = $item->nse_modified_at->gt($item->nse_created_at);
    $modifiedToday = $item->nse_modified_at->isToday();
    $isModified = $afterCreation && $modifiedToday;
    }

    $currentPath = url()->current();
@endphp
<tr class="bg-white border-b border-gray-200 hover:bg-gray-50">
    <td class="p-4">
        @if (!$isFolder)
        <input type="checkbox" value="{{ $item->id }}"
            onchange="checkSelection()"
            class="row-selector w-4 h-4 custom-checkbox rounded border-gray-300">
        @endif
    </td>
    <td class="px-6 py-4 text-gray-700 font-medium" style="display:none;">
        {{ $item->type }}
    </td>
    <td scope="row" class="px-6 py-4 font-medium text-gray-900 flex items-center gap-3 folder_col">
        <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-lg">
            <i data-lucide="{{ $isFolder ? 'folder' : 'file' }}"
                class="w-5 h-5 {{ $isFolder ? 'text-yellow-500 fill-yellow-500/20' : 'text-indigo-600' }}"></i>
        </div>
        <span class="break-all">{{ $item->name }}</span>
    </td>
    <td class="px-6 py-4 text-gray-700 font-medium CreatedDate">
        {{ $item->nse_created_at ? $item->nse_created_at->setTimezone('Asia/Kolkata')->format('Y-m-d h:i a') : '' }}
    </td>
    <td class="px-6 py-4 text-gray-700 font-medium LastUpdate">
        <div class="flex flex-col">
            <span>{{ $item->nse_modified_at ? $item->nse_modified_at->setTimezone('Asia/Kolkata')->format('Y-m-d h:i a') : '' }}</span>
            @if ($isModified)
            <span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Modified
            </span>
            @elseif ($modifiedToday)
            <span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> New
            </span>
            @endif
        </div>
    </td>
    <td class="px-6 py-4 text-left action_col">
        <div class="flex items-center gap-3">
            @if (!$isFolder)
            <button onclick="triggerDownload(this, {{ $item->id }})"
                class="inline-flex items-center font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors download_open">
                <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                Download
            </button>
            @else
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
<tr class="bg-white border-b border-gray-200">
    <td colspan="6" class="px-6 py-16 text-center">
        <div class="flex flex-col items-center justify-center">
            <div class="p-4 bg-gray-50 rounded-full">
                <i data-lucide="cloud-off" class="w-12 h-12 text-gray-300"></i>
            </div>
            
            <h3 class="mt-4 text-lg font-semibold text-gray-700">No activity found</h3>
            
            <p class="text-sm text-gray-500 max-w-xs mx-auto">
                We couldn't find any records here. Try clicking <span class="font-bold text-brand">Sync Now</span> to fetch the latest files.
            </p>
        </div>
    </td>
</tr>
@endforelse