<?php

namespace App\Jobs;

use App\Models\NseCommanContent;
use App\Services\NSECommanService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncNseCommaonFolders implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 3;
    public $uniqueFor = 100;

    private string $segment;
    private string $folder;

    public function __construct(string $segment, string $folder = '')
    {
        $this->segment   = Str::upper($segment);

        // 🔥 normalize once here
        $this->folder = $this->normalizePath($folder);
    }

    public function uniqueId()
    {
        return $this->segment; // prevents duplicate crawlers
    }

    public function handle(NSECommanService $nseCommanService)
    {
        $authToken = $nseCommanService->getAuthToken();

        if ( !$authToken ) {
            Log::channel('syncron')->info("Starting NSE Member sync -- Login token Gen. Failed", [
                'segment' => $this->segment,
                'root' => $this->folder ?: '(root)'
            ]);
            return false;
        }

        Log::channel('syncron')->info("Starting NSE Common sync", [
            'segment' => $this->segment,
            'root' => $this->folder ?: '(root)'
        ]);

        $this->syncFolderRecursive(
            $nseCommanService,
            $authToken,
            $this->segment,
            $this->folder
        );

        Log::channel('syncron')->info("NSE sync completed", [
            'segment' => $this->segment
        ]);
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
        NSECommanService $service,
        string $authToken,
        string $segment,
        string $currentPath = ''
    ): void {

        // Prevent memory blow from query logging
        DB::connection()->disableQueryLog();

        $currentPath = $this->normalizePath($currentPath);

        Log::channel('syncron')->info("Fetching folder", [
            'segment' => $segment,
            'folderPath' => $currentPath ?: '(root)'
        ]);

        $apiResponse = retry(3, function () use (
            $service,
            $authToken,
            $segment,
            $currentPath
        ) {

            return $service->getFolderFilesList(
                $authToken,
                $segment,
                $currentPath
            );
        }, 2000);

        if (empty($apiResponse['data']) || !is_array($apiResponse['data'])) {
            return;
        }

        $existingToday = NseCommanContent::where('segment', $segment)
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

                NseCommanContent::create([
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
            if ($shouldDownload && $type === 'File') {

                $storagePath = storage_path(
                    'app/nse/common/' .
                        $today . '/' .
                        $segment . '/' .
                        ($parent !== 'root' ? $parent . '/' : '')
                );

                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }

                $savePath = $storagePath . $name;

                $nseCommanService->downloadFileFromApi(
                    $authToken,
                    $segment,
                    $currentPath,
                    $name,
                    $savePath
                );
            }
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
                    $nseCommanService,
                    $authToken,
                    $segment,
                    $nextPath
                );
            }
        }
    }
}
