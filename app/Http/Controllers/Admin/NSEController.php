<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NseContent;
use App\Jobs\SyncNseFileJob;
use App\Jobs\SyncNseFolders;
use App\Services\NSEService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class NSEController extends Controller
{
    private $nseService, $perPage;

    public function __construct(NSEService $nseService)
    {
        $this->nseService = $nseService;
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
        $segment = Str::upper($segment);
        $currentFolder = $request->query('folder') ?? 'root';
        $today = Carbon::today();

        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder . '_today');
        $lastSynced = Cache::get($cacheKey . '_time');
        $lastSyncedFormatted = $lastSynced
            ? Carbon::parse($lastSynced)->format('h:i:s A')
            : 'Never';

        /*
    |--------------------------------------------------------------------------
    | STEP 1 — Get today's modified items
    |--------------------------------------------------------------------------
    */
        $todayItems = NseContent::where('segment', $segment)
            ->whereDate('nse_modified_at', $today)
            ->get(['path']);

        if ($todayItems->isEmpty()) {
            return view('admin.nse.segment_folder_today', [
                'segment'     => $segment,
                'folder'      => $currentFolder,
                'contents'    => collect(),
                'lastSynced'  => $lastSyncedFormatted
            ]);
        }

        /*
    |--------------------------------------------------------------------------
    | STEP 2 — Collect all parent paths
    |--------------------------------------------------------------------------
    */
        $requiredPaths = collect();

        foreach ($todayItems as $item) {

            $parts = explode('/', $item->path);

            $accumulated = '';

            foreach ($parts as $index => $part) {

                $accumulated = $index === 0
                    ? $part
                    : $accumulated . '/' . $part;

                $requiredPaths->push($accumulated);
            }
        }

        $requiredPaths = $requiredPaths->unique();

        /*
    |--------------------------------------------------------------------------
    | STEP 3 — Load current folder contents
    |--------------------------------------------------------------------------
    */
        $contents = NseContent::where('segment', $segment)
            ->where(function ($query) use ($currentFolder) {
                if ($currentFolder === 'root') {
                    $query->where('parent_folder', 'root');
                } else {
                    $query->where('parent_folder', $currentFolder);
                }
            })
            ->where(function ($query) use ($today, $requiredPaths) {
                $query->whereDate('nse_modified_at', $today)
                    ->orWhereIn('path', $requiredPaths);
            })
            ->orderByDesc('type')
            ->orderByDesc('nse_modified_at')
            ->get();

        return view('admin.nse.segment_folder_today', [
            'segment'     => $segment,
            'folder'      => $currentFolder,
            'contents'    => $contents,
            'lastSynced'  => $lastSyncedFormatted
        ]);
    }


    public function syncMemberSegment($segment)
    {
        SyncNseFolders::dispatch(
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

        $records = NseContent::where('segment', $segment)
            ->whereDate('nse_modified_at', '<', $today)
            ->orderByDesc('nse_modified_at')
            ->get();

        if ($records->isEmpty()) {
            return view('admin.nse.segment_folder_archives', [
                'segment' => $segment,
                'treeByDate' => collect()
            ]);
        }

        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder . '_today');
        $lastSynced = Cache::get($cacheKey . '_time');
        $lastSyncedFormatted = $lastSynced
            ? Carbon::parse($lastSynced)->format('h:i:s A')
            : 'Never';

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

        return view('admin.nse.segment_folder_archives', [
            'segment' => $segment,
            'treeByDate' => $treeByDate,
            'lastSynced' => $lastSyncedFormatted
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

            $source = $request->query('source', 'today');
            // today | archive

            $today = now()->toDateString();
            $fileRecord = NseContent::findOrFail($id);

            /*
        |--------------------------------------------------------------------------
        | ARCHIVE MODE → NEVER call API
        |--------------------------------------------------------------------------
        */
            if ($source === 'archive') {

                $storagePath = storage_path(
                    'app/nse/' .
                    $fileRecord->nse_modified_at->toDateString() . '/' .
                    $fileRecord->segment . '/' .
                    $fileRecord->parent_folder . '/' .
                    $fileRecord->name
                );

                if (!file_exists($storagePath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File not available in archive storage.'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'url' => route('nse.file.serve', ['id' => $id])
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | TODAY MODE → Download if missing
        |--------------------------------------------------------------------------
        */

            $authToken = $this->nseService->getAuthToken();

            if (!$authToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed.'
                ], 401);
            }

            SyncNseFileJob::dispatchSync($id, $authToken);

            return response()->json([
                'success' => true,
                'url' => route('nse.file.serve', ['id' => $id])
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function serveFile($id)
    {
        $fileRecord = NseContent::findOrFail($id);

        /*
    |--------------------------------------------------------------------------
    | Resolve date folder from DB
    |--------------------------------------------------------------------------
    */
        if (!$fileRecord->created_at) {
            abort(404, 'Invalid file record.');
        }

        $dateFolder = $fileRecord->created_at->format('Y-m-d');

        $relativePath = 'nse/' .
            $dateFolder . '/' .
            $fileRecord->segment . '/' .
            ($fileRecord->parent_folder !== 'root'
                ? $fileRecord->parent_folder . '/'
                : '') .
            $fileRecord->name;

        if (!Storage::exists($relativePath)) {
            abort(404, 'File not found on server.');
        }

        return Storage::download($relativePath, $fileRecord->name);
    }


    public function prepareBulkDownload(Request $request)
    {
        try {

            $authToken = $this->nseService->getAuthToken();
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No files selected.'], 400);
            }

            foreach ($ids as $id) {
                try {
                    SyncNseFileJob::dispatchSync($id, $authToken);
                } catch (\Exception $e) {
                    Log::error("Failed to sync file ID $id: " . $e->getMessage());
                }
            }

            $files = NseContent::whereIn('id', $ids)->get();

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
                'url' => route('nse.bulk.serve', ['filename' => $zipFileName])
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
