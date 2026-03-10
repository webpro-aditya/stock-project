<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NseContent;
use App\Services\NSEService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncNseFileJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileId;
    protected $authToken;
    protected $source;
    protected $archiveDate;

    public function __construct($fileId, $authToken = null, $source = 'today', $archiveDate = '')
    {
        $this->fileId      = $fileId;
        $this->authToken   = $authToken;
        $this->source      = $source;
        $this->archiveDate = $archiveDate;
    }

    public function handle(NSEService $nseService)
    {
        $fileRecord = NseContent::findOrFail($this->fileId);

        /*
        |--------------------------------------------------------------------------
        | Determine Date Folder
        |--------------------------------------------------------------------------
        */
        $dateFolder = $this->source === 'archive' && $this->archiveDate
            ? Carbon::parse($this->archiveDate)->format('Y-m-d')
            : Carbon::parse($fileRecord->created_at)->format('Y-m-d');

        if ($fileRecord->parent_folder == 'root') {
            $relativePath = "nse/{$dateFolder}/{$fileRecord->segment}/{$fileRecord->name}";
        } else {
            $relativePath = "nse/{$dateFolder}/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";
        }

        $absolutePath      = Storage::path($relativePath);
        $fileExistsLocally = Storage::exists($relativePath);

        /*
        |--------------------------------------------------------------------------
        | Resolve folder param
        |--------------------------------------------------------------------------
        */
        $folderParam = (
            $fileRecord->parent_folder === 'root' ||
            $fileRecord->parent_folder === ''     ||
            strtolower($fileRecord->parent_folder) === 'root'
        ) ? '' : $fileRecord->parent_folder;

        /*
        |--------------------------------------------------------------------------
        | Ensure directory exists
        |--------------------------------------------------------------------------
        */
        $directory = dirname($relativePath);
        if (!Storage::exists($directory)) {
            Storage::makeDirectory($directory);
        }

        /*
        |--------------------------------------------------------------------------
        | ARCHIVE MODE — Download only if file missing
        |--------------------------------------------------------------------------
        */
        if ($this->source === 'archive') {

            if (!$this->authToken) {
                throw new \Exception("Missing auth token for archive download.");
            }

            $finalPath = $nseService->downloadFileFromApi(
                $this->authToken,
                $fileRecord->segment,
                $folderParam,
                $fileRecord->name,
                $absolutePath
            );

            if (!$finalPath) {
                throw new \Exception("Failed to download archive file: {$fileRecord->name}");
            }

            Log::info("Archive download complete: $finalPath");
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | TODAY MODE — Download if missing or NSE has newer version
        |--------------------------------------------------------------------------
        */
        $shouldDownload = true;

        if ($fileExistsLocally) {
            $localModified = Carbon::createFromTimestamp(filemtime($absolutePath));

            if (
                $fileRecord->nse_modified_at &&
                !$fileRecord->nse_modified_at->gt($localModified)
            ) {
                $shouldDownload = false;
            }
        }

        if (!$shouldDownload) {
            Log::info("Skipping download, local file is up to date: {$fileRecord->name}");
            return;
        }

        if (!$this->authToken) {
            throw new \Exception("Missing auth token for NSE API download.");
        }

        $finalPath = $nseService->downloadFileFromApi(
            $this->authToken,
            $fileRecord->segment,
            $folderParam,
            $fileRecord->name,
            $absolutePath
        );

        if (!$finalPath) {
            throw new \Exception("Failed to download file from NSE API: {$fileRecord->name}");
        }

        /*
        |--------------------------------------------------------------------------
        | Update DB Flags
        |--------------------------------------------------------------------------
        */
        $fileRecord->update([
            'is_downloaded'     => 1,
            'download_attempts' => 0
        ]);

        // ✅ touch() uses $finalPath (.csv) not $absolutePath (.csv.gz which is now deleted)
        if ($fileRecord->nse_modified_at) {
            touch($finalPath, $fileRecord->nse_modified_at->timestamp);
        }

        Log::info("Today download complete: $finalPath");
    }
}
