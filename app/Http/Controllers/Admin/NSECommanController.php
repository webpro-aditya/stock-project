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

    /*
|--------------------------------------------------------------------------
| Page Load — Direct DB (No Cache)
|--------------------------------------------------------------------------
*/
    public function getTodaySegmentFolder(Request $request, $segment, $folder)
    {
        $segment       = Str::upper($segment);
        $currentFolder = $request->query('folder') ?? '';
        $parent        = $currentFolder ?: 'root';

        $search    = $request->query('search');
        $sort      = $request->query('sort', 'nse_modified_at');
        $direction = $request->query('direction', 'desc');

        /*
    |--------------------------------------------------------------------------
    | Sync Job
    |--------------------------------------------------------------------------
    */
        $syncJob = SyncJob::where('type', 'common')
            ->where('segment', $segment)
            ->whereDate('created_at', Carbon::today())
            ->first();

        if ($syncJob) {
            $syncJob->touch();
        } else {
            $syncJob = SyncJob::create([
                'type'    => 'common',
                'segment' => $segment,
            ]);
        }

        $lastSyncedFormatted = Carbon::parse($syncJob->updated_at)
            ->timezone('Asia/Kolkata')
            ->format('Y-m-d h:i:s A');

        /*
    |--------------------------------------------------------------------------
    | Direct Query (NO CACHE)
    |--------------------------------------------------------------------------
    */
        $query = NseCommanContent::select([
            'id',
            'name',
            'type',
            'segment',
            'path',
            'parent_folder',
            'nse_created_at',
            'nse_modified_at',
            'is_downloaded'
        ])
            ->where('segment', $segment)
            ->where('parent_folder', $parent);

        /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */
        $allowedSorts = ['name', 'nse_created_at', 'nse_modified_at'];

        if (!in_array($sort, $allowedSorts)) {
            $sort = 'nse_modified_at';
        }

        $query->orderBy('type', 'DESC');
        if (!empty($sort)) {
            $query->orderBy($sort, $direction);
        }

        $contents = $query->paginate($this->perPage);

        /*
    |--------------------------------------------------------------------------
    | Folder Modified Logic
    |--------------------------------------------------------------------------
    */
        $collection = $this->computeFolderModifiedTimes(
            $contents->getCollection(),
            $segment
        );

        $contents->setCollection($collection);

        return view('admin.nse.common.segment_folder_today', [
            'segment'    => $segment,
            'folder'     => $currentFolder,
            'contents'   => $contents,
            'lastSynced' => $lastSyncedFormatted,
        ]);
    }


    /*
|--------------------------------------------------------------------------
| Background Sync (NO CACHE)
|--------------------------------------------------------------------------
*/
    public function syncBackground(Request $request, $segment)
    {
        $segment = Str::upper($segment);
        $folder  = (string) ($request->input('folder') ?? '');
        $parent  = $folder ?: 'root';

        try {

            set_time_limit(0);
            ini_set('memory_limit', '512M');

            \Log::info("Sync STARTED: {$segment} | {$parent}");

            $result = $this->syncMemberSegment($segment, $folder);

            $created = $result['created'] ?? 0;
            $updated = $result['updated'] ?? 0;
            $deleted = $result['deleted'] ?? 0;

            $hasChanges = ($created + $deleted) > 0;

            \Log::info("Sync COMPLETED", [
                'segment' => $segment,
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted
            ]);

            $lastSyncedFormatted = now()
                ->timezone('Asia/Kolkata')
                ->format('h:i a');

            return response()->json([
                'status'     => 'ok',
                'created'    => $created,
                'updated'    => $updated,
                'deleted'    => $deleted,
                'hasChanges' => $hasChanges,
                'message'    => $hasChanges
                    ? "Updated ({$created} new, {$updated} modified, {$deleted} removed)"
                    : "Already up to date",
                'lastSynced' => $lastSyncedFormatted,
            ]);
        } catch (\Throwable $e) {

            \Log::error("Sync FAILED: {$segment} | {$parent} | " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /*
|--------------------------------------------------------------------------
| Sync Dispatcher (UNCHANGED)
|--------------------------------------------------------------------------
*/
    public function syncMemberSegment($segment, $folder)
{
    SyncJob::updateOrCreate(
        ['type' => 'common', 'segment' => $segment],
        ['updated_at' => Carbon::now()]
    );

    $job = new SyncNseCommaonFolders($segment, $folder);

    return app()->call([$job, 'handle']);
}

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */
    private function buildCacheKey(string $segment, string $parent): string
    {
        return 'nse_common_contents_' . Str::slug($segment) . '_' . Str::slug($parent);
    }

    private function buildLockKey(string $segment, string $parent): string
    {
        return 'nse_common_sync_lock_' . Str::slug($segment) . '_' . Str::slug($parent);
    }

    private function buildModifiedCacheKey(string $segment, string $parent): string
    {
        return 'nse_common_modified_' . Str::slug($segment) . '_' . Str::slug($parent);
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

        // ✅ GROUP BY + MAX() in SQL — not PHP-side
        $children = NseCommanContent::where('segment', $segment)
            ->whereIn('parent_folder', $folderPaths)
            ->select('parent_folder', DB::raw('MAX(nse_modified_at) as latest_modified'))
            ->groupBy('parent_folder')
            ->get()
            ->keyBy('parent_folder'); // ✅ O(1) lookup

        return $contents->map(function ($item) use ($children, $segment) {
            // ✅ Skip non-folders immediately
            if ($item->type !== 'Folder') return $item;

            $folderKey = Str::after($item->path, $segment . '/');
            $child     = $children->get($folderKey);

            if ($child && $child->latest_modified) {
                $item->nse_modified_at = Carbon::parse($child->latest_modified);
            }

            return $item;
        });
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
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed.'
                ], 401);
            }

            $source      = $request->query('source', 'today');
            $archiveDate = $request->query('date');

            SyncNseCommonFileJob::dispatchSync($id, $authToken, $source, $archiveDate);

            $routeParams = ['id' => $id];

            // ✅ Pass archiveDate to serveFile so it resolves the correct date folder
            if ($source === 'archive' && $archiveDate) {
                $routeParams['archiveDate'] = $archiveDate;
            }

            return response()->json([
                'success' => true,
                'url'     => route('nse.common.file.serve', $routeParams)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function serveFile(Request $request, $id)  // ✅ Added Request $request
    {
        $fileRecord = NseCommanContent::findOrFail($id);

        // ✅ Correct date resolution — query param takes priority
        $dateFolder = $request->query('archiveDate')
            ?? $fileRecord->created_at->format('Y-m-d');

        // ✅ No 'root' literally in path
        $folderSegment = (!empty($fileRecord->parent_folder) && strtolower($fileRecord->parent_folder) !== 'root')
            ? $fileRecord->parent_folder . '/'
            : '';

        // ✅ Strip .gz — actual file on disk is decompressed
        $storedName = str_ends_with($fileRecord->name, '.gz')
            ? substr($fileRecord->name, 0, -3)
            : $fileRecord->name;

        $relativePath = 'common/' .
            $dateFolder . '/' .
            $fileRecord->segment . '/' .
            $folderSegment .
            $storedName;

        if (!Storage::exists($relativePath)) {
            Log::error("serveFile [common]: File not found at: $relativePath");
            abort(404, 'File not found.');
        }

        // ✅ Serve with correct filename (no .gz)
        return Storage::download($relativePath, $storedName);
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

            $files = NseCommanContent::whereIn('id', $ids)->get();

            if ($files->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Files not found in database.'
                ], 404);
            }

            // ✅ Auth check ONCE before the loop, not inside it
            $authToken = $this->nseCommanService->getAuthToken();

            if (!$authToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed.'
                ], 401);
            }

            /*
        |------------------------------------------------------------------
        | Sync missing files only
        |------------------------------------------------------------------
        */
            foreach ($files as $file) {
                $dateFolder = Carbon::parse($file->created_at)->format('Y-m-d');

                // ✅ No 'root' in path
                $folderSegment = (!empty($file->parent_folder) && strtolower($file->parent_folder) !== 'root')
                    ? $file->parent_folder . '/'
                    : '';

                // ✅ Resolve actual stored name (.gz → .csv)
                $storedName = str_ends_with($file->name, '.gz')
                    ? substr($file->name, 0, -3)
                    : $file->name;

                $relativeFilePath = "common/{$dateFolder}/{$file->segment}/{$folderSegment}{$storedName}";

                // ✅ Only sync if file is actually missing
                if (!Storage::exists($relativeFilePath)) {
                    try {
                        SyncNseCommonFileJob::dispatchSync($file->id, $authToken, 'today');
                    } catch (\Throwable $e) {
                        Log::error("Failed to sync common file ID {$file->id}: " . $e->getMessage());
                    }
                }
            }

            /*
        |------------------------------------------------------------------
        | Create ZIP
        |------------------------------------------------------------------
        */
            $zipFileName     = 'nse_common_bulk_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.zip';
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

            $addedNames = [];
            $addedCount = 0;

            foreach ($files as $file) {
                $dateFolder = Carbon::parse($file->created_at)->format('Y-m-d');

                // ✅ No 'root' in path
                $folderSegment = (!empty($file->parent_folder) && strtolower($file->parent_folder) !== 'root')
                    ? $file->parent_folder . '/'
                    : '';

                // ✅ Actual stored filename (no .gz)
                $storedName = str_ends_with($file->name, '.gz')
                    ? substr($file->name, 0, -3)
                    : $file->name;

                $relativeFilePath = "common/{$dateFolder}/{$file->segment}/{$folderSegment}{$storedName}";

                if (!Storage::exists($relativeFilePath)) {
                    Log::warning("Common bulk ZIP: File missing, skipping: $relativeFilePath");
                    continue;
                }

                $absoluteFilePath = Storage::path($relativeFilePath);

                // ✅ ZIP entry uses storedName (.csv), not $file->name (.csv.gz)
                $zipName = $storedName;

                // ✅ Prevent duplicate filenames inside ZIP
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

            // ✅ Don't serve an empty ZIP
            if ($addedCount === 0) {
                if (file_exists($absoluteZipPath)) unlink($absoluteZipPath);
                return response()->json([
                    'success' => false,
                    'message' => 'No files could be added to the ZIP.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'url'     => route('nse.common.bulk.serve', ['filename' => $zipFileName])
            ]);
        } catch (\Throwable $e) {
            Log::error("Common bulk download failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk download failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function serveBulkZip($filename)
    {
        // ✅ Sanitize to prevent path traversal
        $filename     = basename($filename);
        $relativePath = 'nse_temp_zips/' . $filename;

        if (!Storage::exists($relativePath)) {
            Log::error("serveBulkZip [common]: ZIP not found: $relativePath");
            abort(404, 'ZIP file not found.'); // ✅ proper 404 instead of silent redirect
        }

        $absolutePath = Storage::path($relativePath);

        return response()->download($absolutePath)->deleteFileAfterSend(true);
    }
}
