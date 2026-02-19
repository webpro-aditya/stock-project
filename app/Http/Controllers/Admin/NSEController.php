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

        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder . '_today');
        $lastSynced = Cache::get($cacheKey . '_time');
        $lastSyncedFormatted = $lastSynced
            ? Carbon::parse($lastSynced)->format('h:i:s A')
            : 'Never';

        $currentFolder = $request->query('folder') ?? 'root';

        $contents = NseContent::where('segment', $segment)
            ->where('parent_folder', $currentFolder)
            ->where(function ($query) {
                $query->whereDate('nse_created_at', Carbon::today());
            })
            ->orderByDesc('type') // Folder first
            ->orderByDesc('nse_modified_at') // Latest files first
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
        $authToken = $this->nseService->getAuthToken();

        if (!$authToken) {
            return redirect()->route('nse.index')->withErrors('Unable to authenticate with NSE API. Please try again later.');
        }

        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder);

        if (!Cache::has($cacheKey)) {

            $apiResponse = $this->nseService->getFolderFilesList(
                $authToken,
                Str::upper($segment),
                Str::studly($folder)
            );

            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                $dataToUpsert = [];
                foreach ($apiResponse['data'] as $item) {
                    $dateString = $item['lastUpdated'] ?? $item['lastModified'] ?? null;

                    $dataToUpsert[] = [
                        'segment'         => Str::upper($segment),
                        'parent_folder'   => $folder,
                        'name'            => $item['name'],
                        'type'            => $item['type'],
                        'path'            => Str::upper($segment) . '/' . $folder . '/' . $item['name'],
                        'size'            => $item['size'] ?? 0,
                        'nse_modified_at' => $dateString ? Carbon::parse($dateString) : null,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ];
                }

                if (!empty($dataToUpsert)) {
                    NseContent::upsert($dataToUpsert, ['path'], ['size', 'nse_modified_at', 'updated_at']);
                }

                Cache::put($cacheKey, true, now()->addMinutes(15));
            }
        }

        $todayStartIST = now()->setTimezone('Asia/Kolkata')->startOfDay()->utc();

        $contents = NseContent::where('segment', Str::upper($segment))
            ->where('parent_folder', $folder)
            ->where('type', 'file')
            ->where('nse_modified_at', '<', $todayStartIST)
            ->orderBy('nse_modified_at', 'desc')
            ->orderBy('type', 'desc')
            ->get();

        $groupedContents = $contents->groupBy(function ($item) {
            return $item->nse_modified_at->format('Y-m-d');
        });

        return view('admin.nse.segment_folder_archives', [
            'segment'   => $segment,
            'folder'    => $folder,
            'groupedContents'  => $groupedContents,
            'authToken' => $authToken
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
            $authToken = $this->nseService->getAuthToken();

            if (!$authToken) {
                return response()->json(['success' => false, 'message' => 'Authentication failed.'], 401);
            }
            SyncNseFileJob::dispatchSync($id, $authToken);

            return response()->json([
                'success' => true,
                'url' => route('nse.file.serve', ['id' => $id])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function serveFile($id)
    {
        $fileRecord = NseContent::findOrFail($id);
        $relativePath = "nse_cache/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";

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
