<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncJob;
use App\Models\NseCommanContent;
use App\Jobs\SyncBseFileJob;
use App\Jobs\SyncBseFolders;
use App\Jobs\SyncNseCommonFileJob;
use App\Jobs\SyncNseCommaonFolders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\NSECommanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class NSECommanController extends Controller
{
    private $nseCommanService, $perPage;

    public function __construct(NSECommanService $nseCommanService)
    {
        $this->nseCommanService = $nseCommanService;
        $this->perPage = config('constants.pagination_records_per_page', 15);
    }

    public function index()
    {
        return view('admin.nse.index');
    }

    public function getSegment($segment)
    {
        return view('admin.nse.segment', ['segment' => $segment]);
    }

    public function getTodaySegmentFolder(Request $request, $segment, $folder)
    {
        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder . '_today');
        $lastSyncedCache = Cache::get($cacheKey . '_time');
         $lastSyncedDb = SyncJob::select('updated_at')->where([
            'type' => 'common',
            'segment' => $segment
        ])->first();
        $lastSyncedFormatted = $lastSyncedDb
            ? Carbon::parse($lastSyncedDb->updated_at)->format('Y-m-d h:i:s A') : '';

        $contents = NseCommanContent::where('segment', Str::upper($segment))
            ->where('parent_folder', $folder)
            ->orderBy('type', 'desc')
            ->orderBy('nse_modified_at', 'desc')
            ->get();

        if ($request->has('folder') && $request->query('folder') !== $folder) {
            $contents = NseCommanContent::where('segment', Str::upper($segment))
                ->where('parent_folder', $request->query('folder'))
                ->orderBy('type', 'desc')
                ->orderBy('nse_modified_at', 'desc')
                ->get();
        }

        return view('admin.nse.common.segment_folder_today', [
            'segment'     => $segment,
            'folder'      => $folder,
            'contents'    => $contents,
            'lastSynced'  => $lastSyncedFormatted
        ]);
    }

    public function syncMemberSegment($segment)
    {
        $existingSync = SyncJob::where('type', 'common')
            ->where('segment', $segment)
            ->whereDate('updated_at', Carbon::today())
            ->first();

        if ($existingSync) {
            $existingSync->touch(); 
        } else {
            SyncJob::create([
                'type' => 'common',
                'segment' => $segment,
            ]);
        }

        SyncNseCommaonFolders::dispatch(
            $segment,
            ''
        );

        return response()->json([
            'success' => true,
            'message' => 'NSE sync started in background.'
        ]);
    }

    public function getArchiveSegmentFolder($segment, $folder)
    {
        $segment = Str::upper($segment);
        $today = Carbon::today();

        $records = NseCommanContent::where('segment', $segment)
            ->whereDate('nse_modified_at', '<', $today)
            ->orderByDesc('nse_modified_at')
            ->get();

        if ($records->isEmpty()) {
            return view('admin.nse.common.segment_folder_archives', [
                'segment' => $segment,
                'treeByDate' => collect()
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Group by Date
    |--------------------------------------------------------------------------
    */
        $grouped = $records->groupBy(function ($item) {
            return Carbon::parse($item->nse_modified_at)->format('Y-m-d');
        });

        /*
    |--------------------------------------------------------------------------
    | Build Folder Tree Per Date
    |--------------------------------------------------------------------------
    */
        $treeByDate = [];

        foreach ($grouped as $date => $items) {

            $tree = [];
            foreach ($items as $item) {
                $parts = explode('/', $item->path);
                $current = &$tree;

                foreach ($parts as $index => $part) {

                    if (!isset($current[$part])) {
                        $current[$part] = [
                            '_meta' => null,
                            'children' => []
                        ];
                    }

                    if ($index === count($parts) - 1) {
                        $current[$part]['_meta'] = $item;
                    }

                    $current = &$current[$part]['children'];
                }
            }

            $treeByDate[$date] = $tree;
        }

        return view('admin.nse.common.segment_folder_archives', [
            'segment' => $segment,
            'treeByDate' => $treeByDate
        ]);
    }

    public function clearArchiveFolderCache($segment, $folder)
    {
        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder);

        Cache::forget($cacheKey);

        return response()->json(['success' => true]);
    }

    public function prepareDownload(Request $request, $id)
    {
        try {
            $authToken = $this->nseCommanService->getAuthToken();

            if (!$authToken) {
                return response()->json(['success' => false, 'message' => 'Authentication failed.'], 401);
            }
            SyncNseCommonFileJob::dispatchSync($id, $authToken);
            return response()->json([
                'success' => true,
                'url' => route('nse.common.file.serve', ['id' => $id])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function serveFile($id)
    {
        $fileRecord = NseCommanContent::findOrFail($id);
        $relativePath = "nse_cache/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";

        if (!Storage::exists($relativePath)) {
            abort(404, 'File not found on server.');
        }

        return Storage::download($relativePath, $fileRecord->name);
    }


    public function prepareBulkDownload(Request $request)
    {
        try {
            $authToken = $this->nseCommanService->getAuthToken();

            if (!$authToken) {
                return response()->json(['success' => false, 'message' => 'Authentication failed.'], 401);
            }

            $ids = $request->input('ids', []);
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No files selected.'], 400);
            }

            foreach ($ids as $id) {
                try {
                    SyncNseCommonFileJob::dispatchSync($id, $authToken);
                } catch (\Exception $e) {
                    Log::error("Failed to sync file ID $id: " . $e->getMessage());
                }
            }

            $files = NseCommanContent::whereIn('id', $ids)->get();

            if ($files->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Files not found in database.'], 404);
            }

            $zipFileName = 'nse_bulk_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
            $relativePath = 'nse_temp_zips/' . $zipFileName;
            $absolutePath = Storage::path($relativePath);

            $directory = dirname($absolutePath);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($files as $file) {
                    $fileRelPath = "nse_cache/{$file->segment}/{$file->parent_folder}/{$file->name}";

                    if (Storage::exists($fileRelPath)) {
                        $zip->addFile(Storage::path($fileRelPath), $file->name);
                    }
                }
                $zip->close();
            } else {
                return response()->json(['success' => false, 'message' => 'Failed to create ZIP archive.'], 500);
            }

            return response()->json([
                'success' => true,
                'url' => route('nse.common.bulk.serve', ['filename' => $zipFileName])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function serveBulkZip($filename)
    {
        $filename = basename($filename);
        $relativePath = 'nse_temp_zips/' . $filename;

        if (!Storage::exists($relativePath)) {
            abort(404, 'Zip file expired or not found.');
        }

        $absolutePath = Storage::path($relativePath);

        return response()->download($absolutePath)->deleteFileAfterSend(true);
    }
}
