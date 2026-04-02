<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NseContent;
use App\Models\SyncJob;
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

    // public function getTodaySegmentFolder(Request $request, $segment, $folder)
    // {
    //     $segment = Str::upper($segment);
    //     $currentFolder = $request->query('folder') ?? '';

    //     /*
    // |--------------------------------------------------------------------------
    // | Trigger Folder Sync (Single Level)
    // |--------------------------------------------------------------------------
    // */
    //     $this->syncMemberSegment($segment, $currentFolder);

    //     /*
    // |--------------------------------------------------------------------------
    // | Get Last Synced Time
    // |--------------------------------------------------------------------------
    // */
    //     $lastSyncedDb = SyncJob::where([
    //         'type' => 'member',
    //         'segment' => $segment
    //     ])
    //         ->latest('updated_at')
    //         ->first();

    //     $lastSyncedFormatted = $lastSyncedDb
    //         ? Carbon::parse($lastSyncedDb->updated_at)->format('Y-m-d h:i:s A')
    //         : '';

    //     /*
    // |--------------------------------------------------------------------------
    // | Load Current Folder Data
    // |--------------------------------------------------------------------------
    // */
    //     $parent = $currentFolder ?: 'root';

    //     $contents = NseContent::where('segment', $segment)
    //         ->where('parent_folder', $parent)
    //         ->orderBy('type')      // Folders first
    //         ->orderBy('name')
    //         ->get();

    //     return view('admin.nse.segment_folder_today', [
    //         'segment'    => $segment,
    //         'folder'     => $currentFolder,
    //         'contents'   => $contents,
    //         'lastSynced' => $lastSyncedFormatted
    //     ]);
    // }

    /*
|--------------------------------------------------------------------------
| Page Load — Serve from Cache, Trigger Background Sync via AJAX
|--------------------------------------------------------------------------
*/
    public function getTodaySegmentFolder(Request $request, $segment, $folder)
    {
        $segment       = Str::upper($segment);
        $currentFolder = $request->query('folder') ?? '';
        $parent        = $currentFolder ?: 'root';

        // ✅ Single SyncJob query — upsert + reuse for lastSynced
        $syncJob = SyncJob::where('type', 'member')
            ->where('segment', $segment)
            ->whereDate('created_at', Carbon::today())
            ->first();

        if ($syncJob) {
            $syncJob->touch();
        } else {
            $syncJob = SyncJob::create([
                'type'    => 'member',
                'segment' => $segment,
            ]);
        }

        // ✅ Reuse $syncJob — no second DB query needed
        $lastSyncedFormatted = Carbon::parse($syncJob->updated_at)
            ->timezone('Asia/Kolkata')
            ->format('Y-m-d h:i:s A');

        // ✅ Fetch from cache with specific columns only
        $cacheKey = $this->buildCacheKey($segment, $parent);

        $contents = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($segment, $parent) {
            return NseContent::select([
                'id',
                'name',
                'type',
                'segment',
                'path',
                'parent_folder',
                'path',
                'nse_created_at',
                'nse_modified_at',
                'is_downloaded'
            ])
                ->where('segment', $segment)
                ->where('parent_folder', $parent)
                ->orderBy('type', 'DESC')
                ->orderBy('nse_modified_at', 'DESC')
                ->get();
        });

        // ✅ Cache the computed folder modified times too
        $modifiedCacheKey = $this->buildModifiedCacheKey($segment, $parent);

        $contents = Cache::remember($modifiedCacheKey, now()->addMinutes(15), function () use ($contents, $segment) {
            return $this->computeFolderModifiedTimes($contents, $segment);
        });

        return view('admin.nse.segment_folder_today', [
            'segment'    => $segment,
            'folder'     => $currentFolder,
            'contents'   => $contents,
            'lastSynced' => $lastSyncedFormatted,
        ]);
    }

    /*
|--------------------------------------------------------------------------
| Background Sync Endpoint — Called via AJAX on page load
|--------------------------------------------------------------------------
*/
    public function syncBackground(Request $request, $segment)
    {
        $segment = Str::upper($segment);
        $folder  = (string) ($request->input('folder') ?? '');
        $parent  = $folder ?: 'root';

        $lockKey          = $this->buildLockKey($segment, $parent);
        $cacheKey         = $this->buildCacheKey($segment, $parent);
        $modifiedCacheKey = $this->buildModifiedCacheKey($segment, $parent);

        // Prevent duplicate concurrent syncs for same segment+folder
        if (Cache::has($lockKey)) {
            return response()->json(['status' => 'in_progress']);
        }

        // Acquire lock for max 3 minutes (covers NSE API timeout)
        Cache::put($lockKey, true, now()->addMinutes(3));

        try {
            $this->syncMemberSegment($segment, $folder);

            // ✅ Bust both cache keys — data + modified times
            Cache::forget($cacheKey);
            Cache::forget($modifiedCacheKey);

            // ✅ Use now() directly — sync just completed, no extra DB query needed
            $lastSyncedFormatted = Carbon::now()->timezone('Asia/Kolkata')->format('h:i a');

            return response()->json([
                'status'     => 'ok',
                'lastSynced' => $lastSyncedFormatted,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        } finally {
            // Always release lock regardless of success/failure
            Cache::forget($lockKey);
        }
    }

    /*
|--------------------------------------------------------------------------
| Folder Contents Endpoint — Called via AJAX after sync completes
| Returns rendered HTML partial (avoids rebuilding DOM manually in JS)
|--------------------------------------------------------------------------
*/
    public function getFolderContentsAjax(Request $request, $segment)
    {
        $segment = Str::upper($segment);
        $folder  = $request->query('folder', '');
        $parent  = $folder ?: 'root';

        $cacheKey         = $this->buildCacheKey($segment, $parent);
        $modifiedCacheKey = $this->buildModifiedCacheKey($segment, $parent);

        // ✅ Cache was busted by syncBackground — rebuilds fresh with select()
        $contents = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($segment, $parent) {
            return NseContent::select([
                'id',
                'name',
                'type',
                'segment',
                'path',
                'parent_folder',
                'path',
                'nse_created_at',
                'nse_modified_at',
                'is_downloaded'
            ])
                ->where('segment', $segment)
                ->where('parent_folder', $parent)
                ->orderBy('type', 'DESC')
                ->orderBy('nse_modified_at', 'DESC')
                ->get();
        });

        // ✅ Cache modified times computation
        $contents = Cache::remember($modifiedCacheKey, now()->addMinutes(15), function () use ($contents, $segment) {
            return $this->computeFolderModifiedTimes($contents, $segment);
        });

        $html = view('admin.nse._folder_table_rows', [
            'contents' => $contents,
            'segment'  => $segment,
            'folder'   => $folder,
        ])->render();

        return response()->json([
            'status'     => 'ok',
            'html'       => $html,
            'count'      => $contents->count(),
            // ✅ lastSynced was missing in original — JS needs this
            'lastSynced' => Carbon::now()->timezone('Asia/Kolkata')->format('h:i a'),
        ]);
    }

    /*
|--------------------------------------------------------------------------
| Sync Member Segment — Dispatches Job
|--------------------------------------------------------------------------
*/
    public function syncMemberSegment($segment, $folder)
    {
        // ✅ Use updateOrCreate — cleaner than first()+touch()/create()
        SyncJob::updateOrCreate(
            [
                'type'    => 'member',
                'segment' => $segment,
            ],
            [
                'updated_at' => Carbon::now(),
            ]
        );

        SyncNseFolders::dispatch($segment, $folder);
    }

    /*
|--------------------------------------------------------------------------
| Private Helpers
|--------------------------------------------------------------------------
*/
    private function buildCacheKey(string $segment, string $parent): string
    {
        return 'nse_contents_' . Str::slug($segment) . '_' . Str::slug($parent);
    }

    private function buildLockKey(string $segment, string $parent): string
    {
        return 'nse_sync_lock_' . Str::slug($segment) . '_' . Str::slug($parent);
    }

    // ✅ New helper — for caching computeFolderModifiedTimes result
    private function buildModifiedCacheKey(string $segment, string $parent): string
    {
        return 'nse_modified_' . Str::slug($segment) . '_' . Str::slug($parent);
    }

    private function computeFolderModifiedTimes($contents, string $segment)
    {
        $folderPaths = $contents
            ->where('type', 'Folder')
            ->pluck('path')
            ->map(fn($path) => Str::after($path, $segment . '/'))
            ->toArray();

        if (empty($folderPaths)) {
            return $contents;
        }

        // ✅ GROUP BY + MAX() in SQL — not PHP-side collection grouping
        // Reduces memory usage and is faster on 100+ children
        $children = NseContent::where('segment', $segment)
            ->whereIn('parent_folder', $folderPaths)
            ->select('parent_folder', DB::raw('MAX(nse_modified_at) as latest_modified'))
            ->groupBy('parent_folder')
            ->get()
            ->keyBy('parent_folder'); // ✅ keyBy for O(1) direct access vs O(n) groupBy

        return $contents->map(function ($item) use ($children, $segment) {
            // ✅ Skip non-folders immediately — no unnecessary processing
            if ($item->type !== 'Folder') return $item;

            $folderKey = Str::after($item->path, $segment . '/');
            $child     = $children->get($folderKey);

            if ($child && $child->latest_modified) {
                $item->nse_modified_at = Carbon::parse($child->latest_modified);
            }

            return $item;
        });
    }


    //  public function syncMemberSegment($segment)
    // {

    //     $existingSync = SyncJob::where('type', 'member')
    //         ->where('segment', $segment)
    //         ->whereDate('updated_at', Carbon::today())
    //         ->first();

    //     if ($existingSync) {
    //         $existingSync->touch();
    //     } else {
    //         SyncJob::create([
    //             'type' => 'member',
    //             'segment' => $segment,
    //         ]);
    //     }


    //     // 🔥 MASTER JOB ONLY
    //     SyncSegmentJob::dispatch($segment);
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'NSE sync started sequentially.'
    //     ]);
    // }

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
                'segment'    => $segment,
                'treeByDate' => collect(),
                'lastSynced' => 'Never'
            ]);
        }

        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder . '_today');
        $lastSynced = Cache::get($cacheKey . '_time');

        $lastSyncedFormatted = $lastSynced
            ? Carbon::parse($lastSynced)->format('h:i:s A')
            : 'Never';

        /*
    |--------------------------------------------------------------------------
    | Group Records By Modified Date
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
            $normalizedDate = Carbon::parse($date);

            foreach ($items as $item) {

                /*
            |--------------------------------------------------------------------------
            | Normalize timestamps to match accordion date
            |--------------------------------------------------------------------------
            | This ensures:
            | - nse_created_at matches accordion date
            | - nse_modified_at matches accordion date
            | Without altering DB values
            */
                $originalCreated  = Carbon::parse($item->nse_created_at);
                $originalModified = Carbon::parse($item->nse_modified_at);

                $item->nse_created_at = $originalCreated
                    ->setDate(
                        $normalizedDate->year,
                        $normalizedDate->month,
                        $normalizedDate->day
                    );

                $item->nse_modified_at = $originalModified
                    ->setDate(
                        $normalizedDate->year,
                        $normalizedDate->month,
                        $normalizedDate->day
                    );

                $parts = explode('/', $item->path);
                $current = &$tree;

                foreach ($parts as $index => $part) {

                    if (!isset($current[$part])) {
                        $current[$part] = [
                            '_meta'   => null,
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
            'segment'    => $segment,
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
            $source     = $request->query('source', 'today');
            $fileRecord = NseContent::findOrFail($id);
            $authToken  = $this->nseService->getAuthToken();

            if (!$authToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed.'
                ], 401);
            }

            if ($source === 'archive') {
                $archiveDate = $request->query('date');

                // ✅ Build correct path (no 'root' folder in path)
                $folderSegment = (!empty($fileRecord->parent_folder) && strtolower($fileRecord->parent_folder) !== 'root')
                    ? $fileRecord->parent_folder . '/'
                    : '';

                $storagePath = storage_path(
                    'app/nse/' . $archiveDate . '/' .
                        $fileRecord->segment . '/' .
                        $folderSegment .
                        $fileRecord->name
                );

                if (!file_exists($storagePath)) {
                    SyncNseFileJob::dispatchSync($id, $authToken, 'archive', $archiveDate);
                }

                return response()->json([
                    'success' => true,
                    'url' => route('nse.file.serve', [
                        'id'          => $id,
                        'archiveDate' => $archiveDate  // ✅ pass date as query param
                    ])
                ]);
            }

            // TODAY MODE
            SyncNseFileJob::dispatchSync($id, $authToken, 'today');

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


    public function serveFile(Request $request, $id)
    {
        $fileRecord  = NseContent::findOrFail($id);

        // ✅ Resolve date folder
        $dateFolder = $request->query('archiveDate')
            ?? $fileRecord->created_at->format('Y-m-d');

        // ✅ No 'root' in path
        $folderSegment = (!empty($fileRecord->parent_folder) && strtolower($fileRecord->parent_folder) !== 'root')
            ? $fileRecord->parent_folder . '/'
            : '';

        $baseName = $fileRecord->name;

        // ✅ If original file was .gz, the actual stored file is now .csv (decompressed)
        if (str_ends_with($baseName, '.gz')) {
            $baseName = substr($baseName, 0, -3); // strip .gz
        }

        $relativePath = 'nse/' . $dateFolder . '/' .
            $fileRecord->segment . '/' .
            $folderSegment .
            $baseName;

        if (!Storage::exists($relativePath)) {
            Log::error("serveFile: File not found at: $relativePath");
            abort(404, 'File not found.');
        }

        // ✅ Serve with correct filename (no .gz extension)
        return Storage::download($relativePath, $baseName);
    }



    public function prepareBulkDownload(Request $request)
    {
        try {
            $ids = array_unique($request->input('ids', []));

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No files selected.'
                ], 400);
            }

            $files = NseContent::whereIn('id', $ids)->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Files not found in database.'
                ], 404);
            }

            $authToken = $this->nseService->getAuthToken();

            if (!$authToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed.'
                ], 401);
            }

            /*
        |------------------------------------------------------------------
        | Sync each file if not already downloaded
        |------------------------------------------------------------------
        */
            foreach ($files as $file) {
                $dateFolder = Carbon::parse($file->created_at)->format('Y-m-d');

                // ✅ Correct folder segment — no 'root' in path
                $folderSegment = (!empty($file->parent_folder) && strtolower($file->parent_folder) !== 'root')
                    ? $file->parent_folder . '/'
                    : '';

                // ✅ Resolve actual stored filename (.gz → .csv)
                $storedName = str_ends_with($file->name, '.gz')
                    ? substr($file->name, 0, -3)
                    : $file->name;

                $relativePath = "nse/{$dateFolder}/{$file->segment}/{$folderSegment}{$storedName}";

                // ✅ Only re-download if file is actually missing
                if (!Storage::exists($relativePath)) {
                    try {
                        SyncNseFileJob::dispatchSync($file->id, $authToken, 'today');
                    } catch (\Throwable $e) {
                        Log::error("File sync failed for ID {$file->id}: " . $e->getMessage());
                    }
                }
            }

            /*
        |------------------------------------------------------------------
        | Create ZIP
        |------------------------------------------------------------------
        */
            $zipFileName     = 'nse_bulk_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
            $relativeZipPath = 'nse_temp_zips/' . $zipFileName;
            $absoluteZipPath = Storage::path($relativeZipPath);

            if (!Storage::exists('nse_temp_zips')) {
                Storage::makeDirectory('nse_temp_zips');
            }

            $zip = new ZipArchive;

            if ($zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create ZIP archive.'
                ], 500);
            }

            $addedNames  = [];
            $addedCount  = 0;

            foreach ($files as $file) {
                $dateFolder = Carbon::parse($file->created_at)->format('Y-m-d');

                // ✅ Correct folder segment
                $folderSegment = (!empty($file->parent_folder) && strtolower($file->parent_folder) !== 'root')
                    ? $file->parent_folder . '/'
                    : '';

                // ✅ Actual stored filename (no .gz)
                $storedName = str_ends_with($file->name, '.gz')
                    ? substr($file->name, 0, -3)
                    : $file->name;

                $relativeFilePath = "nse/{$dateFolder}/{$file->segment}/{$folderSegment}{$storedName}";

                if (!Storage::exists($relativeFilePath)) {
                    Log::warning("Bulk ZIP: File missing, skipping: $relativeFilePath");
                    continue;
                }

                $absoluteFilePath = Storage::path($relativeFilePath);

                // ✅ ZIP entry uses storedName (.csv), not $file->name (.csv.gz)
                $zipName = $storedName;

                // ✅ Prevent duplicate filenames inside zip
                if (in_array($zipName, $addedNames)) {
                    $zipName = pathinfo($storedName, PATHINFO_FILENAME)
                        . '_' . $file->id . '.'
                        . pathinfo($storedName, PATHINFO_EXTENSION);
                }

                $zip->addFile($absoluteFilePath, $zipName);
                $addedNames[] = $zipName;
                $addedCount++;
            }

            $zip->close();

            // ✅ Don't return an empty ZIP
            if ($addedCount === 0) {
                if (file_exists($absoluteZipPath)) unlink($absoluteZipPath);
                return response()->json([
                    'success' => false,
                    'message' => 'No files could be added to the ZIP.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'url'     => route('nse.bulk.serve', ['filename' => $zipFileName])
            ]);
        } catch (\Throwable $e) {
            Log::error("Bulk download failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk download failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function serveBulkZip($filename)
    {
        // ✅ Sanitize filename to prevent path traversal
        $filename     = basename($filename);
        $relativePath = 'nse_temp_zips/' . $filename;

        if (!Storage::exists($relativePath)) {
            Log::error("serveBulkZip: ZIP not found: $relativePath");
            abort(404, 'ZIP file not found.');  // ✅ proper 404 instead of silent redirect
        }

        $absolutePath = Storage::path($relativePath);

        return response()->download($absolutePath)->deleteFileAfterSend(true);
    }
}
