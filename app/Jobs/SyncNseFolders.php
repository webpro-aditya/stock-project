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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncNseFolders implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;
    public $uniqueFor = 100;

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

    public function handle(NSEService $nseService)
    {
        $authToken = $nseService->getAuthToken();

        if ( !$authToken ) {
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

    // private function syncFolderRecursive(
    //     NSEService $nseService,
    //     string $authToken,
    //     string $segment,
    //     string $currentPath = ''
    // ): void {

    //     $currentPath = $this->normalizePath($currentPath);

    //     Log::info("Fetching folder", [
    //         'segment' => $segment,
    //         'folderPath' => $currentPath ?: '(root)'
    //     ]);

    //     $apiResponse = retry(3, function () use (
    //         $nseService,
    //         $authToken,
    //         $segment,
    //         $currentPath
    //     ) {

    //         return $nseService->getFolderFilesList(
    //             $authToken,
    //             $segment,
    //             $currentPath
    //         );
    //     }, 2000);

    //     if (empty($apiResponse['data']) || !is_array($apiResponse['data'])) {
    //         return;
    //     }

    //     $rows = [];

    //     foreach ($apiResponse['data'] as $item) {

    //         $name = $this->sanitize($item['name']);
    //         $parent = $currentPath ?: 'root';

    //         $fullPath = ltrim(
    //             $segment . '/' .
    //                 ($parent !== 'root' ? $parent . '/' : '') .
    //                 $name,
    //             '/'
    //         );

    //         $date = $item['lastUpdated']
    //             ?? $item['lastModified']
    //             ?? null;

    //         $rows[] = [
    //             'segment' => $segment,
    //             'parent_folder' => $parent,
    //             'name' => $name,
    //             'type' => $item['type'],
    //             'path' => $fullPath,
    //             'size' => $item['size'] ?? 0,
    //             'nse_modified_at' => $date ? Carbon::parse($date) : null,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ];
    //     }

    //     NseContent::upsert(
    //         $rows,
    //         ['segment', 'parent_folder', 'name'],
    //         ['size', 'nse_modified_at', 'updated_at']
    //     );

    //     usleep(120000);

    //     foreach ($apiResponse['data'] as $item) {

    //         if (($item['type'] ?? null) === 'Folder') {

    //             $nextPath = $currentPath
    //                 ? $currentPath . '/' . $this->sanitize($item['name'])
    //                 : $this->sanitize($item['name']);

    //             $this->syncFolderRecursive(
    //                 $nseService,
    //                 $authToken,
    //                 $segment,
    //                 $nextPath
    //             );
    //         }
    //     }
    // }

    private function syncFolderRecursive(
        NSEService $nseService,
        string $authToken,
        string $segment,
        string $currentPath = ''
    ): void {

        DB::connection()->disableQueryLog();

        $currentPath = $this->normalizePath($currentPath);

        Log::channel('syncron')->info("Fetching folder", [
            'segment' => $segment,
            'folderPath' => $currentPath ?: '(root)'
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

        $batch = [];

        foreach ($apiResponse['data'] as $item) {

            $name   = $this->sanitize($item['name']);
            $parent = $currentPath ?: 'root';

            $fullPath = ltrim(
                $segment . '/' .
                    ($parent !== 'root' ? $parent . '/' : '') .
                    $name,
                '/'
            );

            $date = $item['lastUpdated']
                ?? $item['lastModified']
                ?? null;

            $batch[] = [
                'segment' => $segment,
                'parent_folder' => $parent,
                'name' => $name,
                'type' => $item['type'],
                'path' => $fullPath,
                'size' => $item['size'] ?? 0,
                'nse_modified_at' => $date ? Carbon::parse($date) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) === 500) {

                NseContent::upsert(
                    $batch,
                    ['segment', 'parent_folder', 'name'],
                    ['size', 'nse_modified_at', 'updated_at']
                );

                $batch = [];
            }
        }

        if (!empty($batch)) {

            NseContent::upsert(
                $batch,
                ['segment', 'parent_folder', 'name'],
                ['size', 'nse_modified_at', 'updated_at']
            );
        }

        usleep(config('nse.sleep_microseconds', 150000));

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
