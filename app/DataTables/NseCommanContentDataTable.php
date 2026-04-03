<?php

namespace App\DataTables;

use App\Models\NseCommanContent;
use Illuminate\Support\Str;
use Yajra\DataTables\EloquentDataTable;

class NseCommanContentDataTable extends EloquentDataTable
{
    protected $segment;

    protected $parentFolder;

    public function __construct($segment, $parentFolder)
    {
        $this->segment = Str::upper($segment);
        $this->parentFolder = $parentFolder ?: 'root';
    }

    public function query()
    {
        return NseCommanContent::query()
            ->select([
                'id', 'name', 'type', 'segment', 'path',
                'parent_folder', 'nse_created_at', 'nse_modified_at', 'is_downloaded',
            ])
            ->where('segment', $this->segment)
            ->where('parent_folder', $this->parentFolder);
    }

    public function ajax()
    {
        return datatables()
            ->eloquent($this->query())
            ->addColumn('checkbox', function ($item) {
                if ($item->type == 'Folder') {
                    return '';
                }

                return '<input type="checkbox" value="'.$item->id.'"
                    onchange="checkSelection()"
                    class="row-selector w-4 h-4 custom-checkbox rounded border-gray-300">';
            })
            ->editColumn('name', function ($item) {
                $isFolder = $item->type == 'Folder';
                $icon = $isFolder ? 'folder' : 'file';
                $iconClass = $isFolder ? 'text-yellow-500 fill-yellow-500/20' : 'text-indigo-600';

                if ($isFolder) {
                    $url = 'folder='.$item->parent_folder.'/'.$item->name;
                    $url = str_replace('root/', '', $url);
                    $url = request()->fullUrl().'&'.$url;

                    return '<div class="flex items-center gap-3">
                        <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-lg">
                            <i data-lucide="'.$icon.'" class="w-5 h-5 '.$iconClass.'"></i>
                        </div>
                        <span class="break-all"><a href="'.$url.'" class="hover:text-brand">'.e($item->name).'</a></span>
                    </div>';
                }

                return '<div class="flex items-center gap-3">
                    <div class="w-8 h-8 flex items-center justify-center bg-indigo-100 rounded-lg">
                        <i data-lucide="'.$icon.'" class="w-5 h-5 '.$iconClass.'"></i>
                    </div>
                    <span class="break-all">'.e($item->name).'</span>
                </div>';
            })
            ->editColumn('nse_created_at', function ($item) {
                return $item->nse_created_at
                    ? $item->nse_created_at->setTimezone('Asia/Kolkata')->format('Y-m-d h:i a')
                    : '';
            })
            ->editColumn('nse_modified_at', function ($item) {
                $modifiedHtml = $item->nse_modified_at
                    ? $item->nse_modified_at->setTimezone('Asia/Kolkata')->format('Y-m-d h:i a')
                    : '';

                $isModified = false;
                $modifiedToday = false;

                if ($item->nse_created_at && $item->nse_modified_at) {
                    $afterCreation = $item->nse_modified_at->gt($item->nse_created_at);
                    $modifiedToday = $item->nse_modified_at->isToday();
                    $isModified = $afterCreation && $modifiedToday;
                }

                $badge = '';
                if ($isModified) {
                    $badge = '<span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Modified
                    </span>';
                } elseif ($modifiedToday) {
                    $badge = '<span class="flex items-center gap-1.5 text-xs text-amber-600 font-semibold mt-0.5">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> New
                    </span>';
                }

                return '<div class="flex flex-col">
                    <span>'.$modifiedHtml.'</span>
                    '.$badge.'
                </div>';
            })
            ->addColumn('action', function ($item) {
                $isFolder = $item->type == 'Folder';

                if ($isFolder) {
                    $url = 'folder='.$item->parent_folder.'/'.$item->name;
                    $url = str_replace('root/', '', $url);
                    $url = request()->fullUrl().'&'.$url;

                    return '<div class="flex items-center gap-3">
                        <a href="'.$url.'"
                            class="inline-flex items-center font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors">
                            <i data-lucide="folder-open" class="w-4 h-4 mr-2"></i>
                            Open
                        </a>
                    </div>';
                }

                return '<div class="flex items-center gap-3">
                    <button onclick="triggerDownload(this, '.$item->id.')"
                        class="inline-flex items-center font-semibold text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 px-4 py-2 rounded-lg transition-colors download_open">
                        <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                        Download
                    </button>
                </div>';
            })
            ->rawColumns(['checkbox', 'name', 'nse_modified_at', 'action'])
            ->make(true);
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('activityTable')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->serverSide(true)
            ->processing(true)
            ->pageLength(10)
            ->lengthMenu([[10, 25, 50, 100], [10, 25, 50, 100]])
            ->orderBy(1, 'desc')
            ->orderBy(4, 'desc')
            ->parameters([
                'columnDefs' => [
                    ['orderable' => false, 'targets' => [0, 5]],
                    ['visible' => false, 'targets' => [1]],
                ],
                'language' => [
                    'search' => 'Search files:',
                    'emptyTable' => 'No activity found. Sync to fetch the latest files.',
                ],
            ]);
    }

    protected function getColumns()
    {
        return [
            [
                'data' => 'checkbox',
                'name' => 'checkbox',
                'title' => '',
                'orderable' => false,
                'searchable' => false,
                'width' => '60px',
            ],
            [
                'data' => 'type',
                'name' => 'type',
                'title' => 'Type',
                'visible' => false,
            ],
            [
                'data' => 'name',
                'name' => 'name',
                'title' => 'Folder / File Name',
            ],
            [
                'data' => 'nse_created_at',
                'name' => 'nse_created_at',
                'title' => 'Created',
            ],
            [
                'data' => 'nse_modified_at',
                'name' => 'nse_modified_at',
                'title' => 'Last Updated',
            ],
            [
                'data' => 'action',
                'name' => 'action',
                'title' => 'Action',
                'orderable' => false,
                'searchable' => false,
            ],
        ];
    }

    protected function filename(): string
    {
        return 'NseCommanContent_'.date('YmdHis');
    }
}
