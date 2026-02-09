<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NseContent;
use App\Jobs\SyncNseFileJob;
use App\Services\NSEService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function getTodaySegmentFolder($segment, $folder)
    {
        $sessionData = Session::get('nse_auth_token');
        $now = now()->timestamp;

        $needsNewToken = !$sessionData ||
            !is_array($sessionData) ||
            ($sessionData['expires_at'] ?? 0) < $now ||
            empty($sessionData['value']);

        if ($needsNewToken) {
            $authToken = $this->nseService->getAuthToken();

            if ($authToken) {
                Session::put('nse_auth_token', [
                    'value' => $authToken,
                    'expires_at' => now()->addMinutes(60)->timestamp
                ]);
                Session::save();
            }
        } else {
            $authToken = $sessionData['value'];
        }

        if (!$authToken) {
            return redirect()->route('nse.index')->withErrors('Unable to authenticate with NSE API. Please try again later.');
        }

        $cacheKey = "nse_sync_" . Str::slug($segment . '_' . $folder . '_today');

        if (!Cache::has($cacheKey)) {

            $apiResponse = $this->nseService->getFolderFilesList(
                $authToken,
                Str::upper($segment),
                Str::studly($folder)
            );

            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                $dataToUpsert = [];

                $todayIST = now()->setTimezone('Asia/Kolkata')->toDateString();

                foreach ($apiResponse['data'] as $item) {

                    $dateString = $item['lastModified'] ?? $item['lastUpdated'] ?? null;
                    $fileDate = $dateString ? \Carbon\Carbon::parse($dateString)->setTimezone('Asia/Kolkata') : null;

                    if ($fileDate && $fileDate->toDateString() === $todayIST) {

                        $dataToUpsert[] = [
                            'segment'         => Str::upper($segment),
                            'parent_folder'   => $folder,
                            'name'            => $item['name'],
                            'type'            => ($item['isFolder'] ?? false) ? 'folder' : 'file',
                            'path'            => Str::upper($segment) . '/' . $folder . '/' . $item['name'],
                            'size'            => $item['size'] ?? 0,
                            'nse_modified_at' => $fileDate,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ];
                    }
                }

                if (!empty($dataToUpsert)) {
                    NseContent::upsert($dataToUpsert, ['path'], ['size', 'nse_modified_at', 'updated_at']);
                }

                Cache::put($cacheKey, true, now()->addMinutes(5));
            }
        }

        $todayStart = now()->setTimezone('Asia/Kolkata')->startOfDay()->utc();
        $todayEnd   = now()->setTimezone('Asia/Kolkata')->endOfDay()->utc();

        $contents = NseContent::where('segment', Str::upper($segment))
            ->where('parent_folder', $folder)
            ->where('type', 'file')
            ->whereBetween('nse_modified_at', [$todayStart, $todayEnd])
            ->orderBy('type', 'desc')
            ->orderBy('nse_modified_at', 'desc')
            ->get();

        return view('admin.nse.segment_folder_today', [
            'segment'   => $segment,
            'folder'    => $folder,
            'contents'  => $contents,
            'authToken' => $authToken
        ]);
    }

    public function getArchiveSegmentFolder($segment, $folder)
    {
        $sessionData = Session::get('nse_auth_token');
        $now = now()->timestamp;

        $needsNewToken = !$sessionData ||
            !is_array($sessionData) ||
            ($sessionData['expires_at'] ?? 0) < $now ||
            empty($sessionData['value']);

        if ($needsNewToken) {
            $authToken = $this->nseService->getAuthToken();

            if ($authToken) {
                Session::put('nse_auth_token', [
                    'value' => $authToken,
                    'expires_at' => now()->addMinutes(60)->timestamp
                ]);
                Session::save();
            }
        } else {
            $authToken = $sessionData['value'];
        }

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
                        'type'            => ($item['isFolder'] ?? false) ? 'folder' : 'file',
                        'path'            => Str::upper($segment) . '/' . $folder . '/' . $item['name'],
                        'size'            => $item['size'] ?? 0,
                        'nse_modified_at' => $dateString ? \Carbon\Carbon::parse($dateString) : null,
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

        return view('admin.nse.segment_folder_archives', [
            'segment'   => $segment,
            'folder'    => $folder,
            'contents'  => $contents,
            'authToken' => $authToken
        ]);
    }

    public function downloadFile($id)
    {
        $sessionData = Session::get('nse_auth_token');
        $now = now()->timestamp;

        $needsNewToken = !$sessionData ||
            !is_array($sessionData) ||
            ($sessionData['expires_at'] ?? 0) < $now ||
            empty($sessionData['value']);

        if ($needsNewToken) {
            $authToken = $this->nseService->getAuthToken();
            if ($authToken) {
                Session::put('nse_auth_token', [
                    'value' => $authToken,
                    'expires_at' => now()->addMinutes(60)->timestamp
                ]);
                Session::save();
            }
        } else {
            $authToken = $sessionData['value'];
        }

        if (!$authToken) {
            return redirect()->route('nse.index')->withErrors('Unable to authenticate with NSE API.');
        }

        $fileRecord = NseContent::findOrFail($id);

        $relativePath = "nse_cache/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";
        $absolutePath = Storage::path($relativePath);

        $shouldDownload = true;

        if (Storage::exists($relativePath)) {
            $localTimestamp = Storage::lastModified($relativePath);
            $remoteTimestamp = $fileRecord->nse_modified_at ? $fileRecord->nse_modified_at->timestamp : 0;

            if ($localTimestamp >= $remoteTimestamp) {
                $shouldDownload = false;
            }
        }

        if ($shouldDownload) {
            $directory = dirname($relativePath);
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            $success = $this->nseService->downloadFileFromApi(
                $authToken,
                $fileRecord->segment,
                Str::studly($fileRecord->parent_folder),
                $fileRecord->name,
                $absolutePath
            );

            if (!$success) {
                return back()->withErrors('Failed to download file from NSE.');
            }

            if ($fileRecord->nse_modified_at) {
                touch($absolutePath, $fileRecord->nse_modified_at->timestamp);
            }
        }

        return Storage::download($relativePath, $fileRecord->name);
    }
    
    public function prepareDownload(Request $request, $id)
    {
        try {
            $sessionData = Session::get('nse_auth_token');
            $now = now()->timestamp;
            $needsNewToken = !$sessionData || !is_array($sessionData) || ($sessionData['expires_at'] ?? 0) < $now || empty($sessionData['value']);

            if ($needsNewToken) {
                $authToken = $this->nseService->getAuthToken();
                if ($authToken) {
                    Session::put('nse_auth_token', ['value' => $authToken, 'expires_at' => now()->addMinutes(60)->timestamp]);
                    Session::save();
                }
            } else {
                $authToken = $sessionData['value'];
            }

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
}
