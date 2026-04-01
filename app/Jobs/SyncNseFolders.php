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

    public function __construct(string $segment, ?string $folder = '')
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

        if (!$authToken) {
            Log::channel('syncron')->info("Starting NSE Member sync -- Login token Gen. Failed", [
                'segment' => $this->segment,
                'root' => $this->folder ?: '(root)'
            ]);
            saveSyncLog('member', $this->segment, '401', '803', 'Starting NSE Member sync -- Login token Gen. Failed. Folder: ' . $this->folder ?: '(root)');
            return false;
        }

        Log::channel('syncron')->info("Starting NSE Member sync", [
            'segment' => $this->segment,
            'root' => $this->folder ?: '(root)'
        ]);
        saveSyncLog('member', $this->segment, '200', '', 'Starting NSE Member sync for folder ' . $this->folder ?: '(root)');

        $this->syncSingleFolder(
            $nseService,
            $authToken,
            $this->segment,
            $this->folder
        );

        Log::channel('syncron')->info("NSE sync completed", [
            'segment' => $this->segment
        ]);

        saveSyncLog('member', $this->segment, '200', '', 'NSE sync completed');
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


    private function syncSingleFolder(
        NSEService $nseService,
        string $authToken,
        string $segment,
        string $currentPath = ''
    ): void {

        DB::connection()->disableQueryLog();

        $currentPath = $this->normalizePath($currentPath);
        $today = now()->toDateString();
        $parent = $currentPath ?: 'root';

        Log::channel('syncron')->info("Fetching folder (single level)", [
            'segment' => $segment,
            'folderPath' => $parent
        ]);

        saveSyncLog('member', $this->segment, '200', '', 'Fetching folder ' . $parent);

        try {
            $apiResponse = $nseService->getFolderFilesList(
                $authToken,
                $segment,
                $currentPath
            );
        } catch (\Throwable $e) {

            Log::channel('syncron')->error("API error while fetching folder", [
                'segment' => $segment,
                'folder'  => $currentPath ?: 'root',
                'error'   => $e->getMessage()
            ]);

            saveSyncLog(
                'member',
                $this->segment,
                '500',
                '',
                'API error while fetching folder ' . ($currentPath ?: 'root')
            );

            return;
        }

        if (empty($apiResponse['data']) || !is_array($apiResponse['data'])) {
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Preload today's snapshot for this folder only
    |--------------------------------------------------------------------------
    */
        $existingToday = NseContent::where('segment', $segment)
            ->where('parent_folder', $parent)
            ->get()
            ->keyBy('name');

        $apiNames = [];

        foreach ($apiResponse['data'] as $item) {

            $type = $item['type'] ?? null;
            $name = $this->sanitize($item['name']);

            $apiNames[] = $name;

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
            } else {

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
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Delete records that no longer exist in API
        |--------------------------------------------------------------------------
        */

        $dbNames = $existingToday->keys()->toArray();

        $toDelete = array_diff($dbNames, $apiNames);

        if (!empty($toDelete)) {

            NseContent::where('segment', $segment)
                ->where('parent_folder', $parent)
                ->whereIn('name', $toDelete)
                ->delete();

            Log::channel('syncron')->info("Removed deleted NSE files", [
                'segment' => $segment,
                'folder' => $parent,
                'count' => count($toDelete)
            ]);
        }
    }
}
