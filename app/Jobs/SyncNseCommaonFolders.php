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

    public $timeout = 2800;
    public $tries = 3;
    public $uniqueFor = 10;

    private string $segment;
    private string $folder;

    public function __construct(string $segment, string $folder = '')
    {
        $this->segment = Str::upper($segment);
        $this->folder  = $this->normalizePath($folder);
    }

    public function uniqueId()
    {
        return $this->segment;
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN HANDLE (WITH CHANGE TRACKING)
    |--------------------------------------------------------------------------
    */
    public function handle(NSECommanService $NSECommanService)
    {
        $created = 0;
        $updated = 0;
        $deleted = 0;

        $authToken = $NSECommanService->getAuthToken();

        if (!$authToken) {
            Log::channel('syncron')->info("Token failed", [
                'segment' => $this->segment
            ]);
            return ['created' => 0, 'updated' => 0, 'deleted' => 0];
        }

        Log::channel('syncron')->info("Sync started", [
            'segment' => $this->segment,
            'folder'  => $this->folder ?: '(root)'
        ]);

        $result = $this->syncSingleFolder(
            $NSECommanService,
            $authToken,
            $this->segment,
            $this->folder
        );

        \Log::info("COUNT in JOB", [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'deleted' => $result['deleted']
        ]);

        $created += $result['created'];
        $updated += $result['updated'];
        $deleted += $result['deleted'];

        Log::channel('syncron')->info("Sync completed", [
            'segment' => $this->segment,
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted
        ];
    }

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

    /*
    |--------------------------------------------------------------------------
    | SYNC LOGIC (WITH COUNTERS)
    |--------------------------------------------------------------------------
    */
    private function syncSingleFolder(
        NSECommanService $NSECommanService,
        string $authToken,
        string $segment,
        string $currentPath = ''
    ): array {

        DB::connection()->disableQueryLog();

        $created = 0;
        $updated = 0;
        $deleted = 0;

        $currentPath = $this->normalizePath($currentPath);
        $parent = $currentPath ?: 'root';

        try {
            $apiResponse = $NSECommanService->getFolderFilesList(
                $authToken,
                $segment,
                $currentPath
            );
        } catch (\Throwable $e) {
            Log::error("API error", ['error' => $e->getMessage()]);
            return ['created' => 0, 'updated' => 0, 'deleted' => 0];
        }

        if (empty($apiResponse['data']) || !is_array($apiResponse['data'])) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0];
        }

        $existingToday = NseCommanContent::where('segment', $segment)
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

            /*
            |--------------------------------------------------------------------------
            | CREATE
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

                $created++;
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE
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

                    $updated++;
                }
            }
        }

        \Log::info("SYNC SINGLE COUNT AFTER LOOP", [
            'segment' => $segment,
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted
        ]);

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */
        $dbNames = $existingToday->keys()->toArray();
        $toDelete = array_diff($dbNames, $apiNames);

        if (!empty($toDelete)) {

            $count = count($toDelete);

            NseCommanContent::where('segment', $segment)
                ->where('parent_folder', $parent)
                ->whereIn('name', $toDelete)
                ->delete();

            $deleted += $count;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted
        ];
    }
}
