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

    /**
     * Recursive crawler
     */
    // private function syncFolderRecursive(
    //     NSECommanService $NSECommanService,
    //     string $authToken,
    //     string $segment,
    //     string $currentPath = ''
    // ): void {

    //     $currentPath = $this->normalizePath($currentPath);

    //     Log::info("Fetching folder", [
    //         'segment' => $segment,
    //         'folderPath' => $currentPath ?: '(root)'
    //     ]);

    //     /**
    //      * Retry protects against random NSE failures.
    //      */
    //     $apiResponse = retry(3, function () use (
    //         $NSECommanService,
    //         $authToken,
    //         $segment,
    //         $currentPath
    //     ) {

    //         return $NSECommanService->getFolderFilesList(
    //             $authToken,
    //             $segment,
    //             $currentPath // MUST be empty for root
    //         );

    //     }, 2000);

    //     if (empty($apiResponse['data']) || !is_array($apiResponse['data'])) {
    //         Log::warning("Empty folder", [
    //             'path' => $currentPath ?: '(root)'
    //         ]);
    //         return;
    //     }

    //     $rows = [];

    //     foreach ($apiResponse['data'] as $item) {

    //         $fullPath = ltrim(
    //             ($currentPath ? $currentPath.'/' : '') .
    //             $item['name'],
    //             '/'
    //         );

    //         $date = $item['lastUpdated']
    //             ?? $item['lastModified']
    //             ?? null;

    //         $rows[] = [
    //             'segment' => $segment,
    //             'parent_folder' => $currentPath ?: 'root',
    //             'name' => $item['name'],
    //             'type' => $item['type'],
    //             'path' => $fullPath,
    //             'size' => $item['size'] ?? 0,
    //             'nse_modified_at' => $date ? Carbon::parse($date) : null,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ];
    //     }

    //     /**
    //      * Bulk upsert (fast + safe)
    //      */
    //     NseCommanContent::upsert(
    //         $rows,
    //         ['path'],
    //         ['size', 'nse_modified_at', 'updated_at']
    //     );

    //     /**
    //      * Dive into subfolders
    //      */
    //     foreach ($apiResponse['data'] as $item) {

    //         if (($item['type'] ?? null) === 'Folder') {

    //             $nextPath = $currentPath
    //                 ? $currentPath.'/'.$item['name']
    //                 : $item['name'];

    //             $this->syncFolderRecursive(
    //                 $NSECommanService,
    //                 $authToken,
    //                 $segment,
    //                 $nextPath
    //             );
    //         }
    //     }
    // }

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

            Log::channel('syncron')->warning("Empty folder", [
                'path' => $currentPath ?: '(root)'
            ]);

            return;
        }

        $batch = [];

        foreach ($apiResponse['data'] as $item) {

            $fullPath = ltrim(
                ($currentPath ? $currentPath . '/' : '') .
                    $item['name'],
                '/'
            );

            $date = $item['lastUpdated']
                ?? $item['lastModified']
                ?? null;

            $batch[] = [
                'segment' => $segment,
                'parent_folder' => $currentPath ?: 'root',
                'name' => $item['name'],
                'type' => $item['type'],
                'path' => $fullPath,
                'size' => $item['size'] ?? 0,
                'nse_modified_at' => $date ? Carbon::parse($date) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // 🔥 Flush batch safely
            if (count($batch) === 500) {

                NseCommanContent::upsert(
                    $batch,
                    ['path'],
                    ['size', 'nse_modified_at', 'updated_at']
                );

                $batch = [];
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {

            NseCommanContent::upsert(
                $batch,
                ['path'],
                ['size', 'nse_modified_at', 'updated_at']
            );
        }

        // Rate limiting (important for NSE gateway)
        usleep(config('nse.sleep_microseconds', 150000));

        /**
         * Dive into subfolders
         */
        foreach ($apiResponse['data'] as $item) {

            if (($item['type'] ?? null) === 'Folder') {

                $nextPath = $currentPath
                    ? $currentPath . '/' . $item['name']
                    : $item['name'];

                $this->syncFolderRecursive(
                    $service,
                    $authToken,
                    $segment,
                    $nextPath
                );
            }
        }
    }
}
