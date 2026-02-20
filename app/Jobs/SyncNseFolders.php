<?php

namespace App\Jobs;

use App\Models\NseContent;
use App\Services\NSEService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncNseFolders implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 2800; // 30 minutes
    public $tries = 3;
    public $uniqueFor = 10;

    private string $segment;
    private string $folder;

    public function __construct(string $segment, string $folder = '')
    {
        $this->segment   = Str::upper($segment);
        $this->folder = $this->normalizePath($folder);
    }

    public function uniqueId()
    {
        return $this->segment;
    }

    private function updateProgress(int $current, int $total): void
    {
        $percentage = $total > 0 ? intval(($current / $total) * 100) : 0;

        Cache::put("nse_sync_progress_{$this->segment}", [
            'current' => $current,
            'total' => $total,
            'percentage' => $percentage,
            'status' => 'running'
        ], now()->addMinutes(60));
    }

    public function handle(NSEService $nseService)
    {
        Cache::put("nse_sync_progress_{$this->segment}", [
            'current' => 0,
            'total' => 0,
            'percentage' => 0,
            'status' => 'starting'
        ], now()->addMinutes(60));

        $authToken = $nseService->getAuthToken();

        if (!$authToken) {
            Log::channel('syncron')->info("Starting NSE Member sync -- Login token Gen. Failed", [
                'segment' => $this->segment,
                'root' => $this->folder ?: '(root)'
            ]);
            return false;
        }

        Log::channel('syncron')->info("Starting NSE Member sync", [
            'segment' => $this->segment,
            'root' => $this->folder ?: '(root)'
        ]);

        $this->syncFolderRecursive(
            $nseService,
            $authToken,
            $this->segment,
            $this->folder
        );

        Log::channel('syncron')->info("NSE sync completed", [
            'segment' => $this->segment
        ]);

        Cache::put("nse_sync_progress_{$this->segment}", [
            'current' => 100,
            'total' => 100,
            'percentage' => 100,
            'status' => 'completed'
        ], now()->addMinutes(10));
    }

    /**
     * Normalize folder paths.
     */
    private function normalizePath(?string $path): string
    {
        if (!$path || strtolower($path) === 'root') {
            return '';
        }

        return trim($path, '/');
    }

    private function sanitize(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }


    private function syncFolderRecursive(
        NSEService $nseService,
        string $authToken,
        string $segment,
        string $currentPath = ''
    ): void {

        DB::connection()->disableQueryLog();

        $currentPath = $this->normalizePath($currentPath);
        $today = now()->toDateString();
        $parent = $currentPath ?: 'root';

        Log::channel('syncron')->info("Fetching folder", [
            'segment' => $segment,
            'folderPath' => $parent
        ]);

        $apiResponse = retry(3, function () use (
            $nseService,
            $authToken,
            $segment,
            $currentPath
        ) {
            return $nseService->getFolderFilesList(
                $authToken,
                $segment,
                $currentPath
            );
        }, 2000);

        if (empty($apiResponse['data']) || !is_array($apiResponse['data'])) {
            return;
        }

        $totalItems = count($apiResponse['data']);

        $progress = Cache::get("nse_sync_progress_{$this->segment}");
        $currentCount = $progress['current'] ?? 0;

        /*
    |--------------------------------------------------------------------------
    | Preload today's snapshot for this folder
    |--------------------------------------------------------------------------
    */
        $existingToday = NseContent::where('segment', $segment)
            ->where('parent_folder', $parent)
            ->whereDate('created_at', $today)
            ->get()
            ->keyBy('name');

        foreach ($apiResponse['data'] as $item) {

            $type = $item['type'] ?? null;
            $name = $this->sanitize($item['name']);

            $fullPath = ltrim(
                $segment . '/' .
                    ($parent !== 'root' ? $parent . '/' : '') .
                    $name,
                '/'
            );

            $apiDate = null;

            if (!empty($item['lastUpdated']) || !empty($item['lastModified'])) {
                $apiDate = Carbon::parse(
                    $item['lastUpdated'] ?? $item['lastModified']
                );
            }

            $existing = $existingToday[$name] ?? null;
            $shouldDownload = false;

            /*
        |--------------------------------------------------------------------------
        | CASE 1 — First time today
        |--------------------------------------------------------------------------
        */
            if (!$existing) {

                NseContent::create([
                    'segment' => $segment,
                    'parent_folder' => $parent,
                    'name' => $name,
                    'type' => $type,
                    'path' => $fullPath,
                    'size' => $item['size'] ?? 0,
                    'nse_created_at' => $apiDate,
                    'nse_modified_at' => $apiDate,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                if ($type === 'File') {
                    $shouldDownload = true;
                }
            }

            /*
        |--------------------------------------------------------------------------
        | CASE 2 — Already exists today
        |--------------------------------------------------------------------------
        */ else {

                if (
                    $apiDate &&
                    $existing->nse_modified_at &&
                    $apiDate->gt($existing->nse_modified_at)
                ) {

                    $existing->update([
                        'size' => $item['size'] ?? 0,
                        'nse_modified_at' => $apiDate,
                        'updated_at' => now()
                    ]);

                    if ($type === 'File') {
                        $shouldDownload = true;
                    }
                }

                /*
            IMPORTANT:
            nse_created_at remains untouched.
            */
            }

            /*
        |--------------------------------------------------------------------------
        | Download only if file & needed
        |--------------------------------------------------------------------------
        */
            // Inside your foreach loop where you download the file:

            if ($shouldDownload && $type === 'File') {
                $storagePath = storage_path('app/nse/' . $today . '/' . $segment . '/' . ($parent !== 'root' ? $parent . '/' : ''));

                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }

                $savePath = $storagePath . $name;

                // Example of wrapping the download in a try-catch to handle 401s
                try {
                    $nseService->downloadFileFromApi($authToken, $segment, $currentPath, $name, $savePath);
                } catch (\Exception $e) {
                    // Assuming your service throws an exception on 401. 
                    // Adjust the condition based on how your service returns HTTP status codes.
                    if ($e->getCode() == 401) {
                        Log::channel('syncron')->warning("Token expired mid-download. Refreshing token.");

                        // Fetch a new token
                        $authToken = $nseService->getAuthToken();

                        // Retry the download with the new token
                        $nseService->downloadFileFromApi($authToken, $segment, $currentPath, $name, $savePath);
                    } else {
                        // Rethrow or log other errors (like the 504s)
                        Log::channel('syncron')->error("Download failed: " . $e->getMessage());
                    }
                }
            }

            $currentCount++;
            $this->updateProgress($currentCount, $totalItems);
        }

        /*
    |--------------------------------------------------------------------------
    | Recursive folder traversal
    |--------------------------------------------------------------------------
    */
        foreach ($apiResponse['data'] as $item) {

            if (($item['type'] ?? null) === 'Folder') {

                $nextPath = $currentPath
                    ? $currentPath . '/' . $this->sanitize($item['name'])
                    : $this->sanitize($item['name']);

                $this->syncFolderRecursive(
                    $nseService,
                    $authToken,
                    $segment,
                    $nextPath
                );
            }
        }
    }
}
