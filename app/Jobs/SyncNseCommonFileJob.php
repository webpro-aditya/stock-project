<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NseCommanContent;
use App\Services\NSECommanService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncNseCommonFileJob
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

    public function handle(NSECommanService $nseService)
    {
        $fileRecord = NseCommanContent::findOrFail($this->fileId);

        /*
        |--------------------------------------------------------------------------
        | Determine Date Folder
        |--------------------------------------------------------------------------
        */
        $dateFolder = $this->source === 'archive' && $this->archiveDate
            ? Carbon::parse($this->archiveDate)->format('Y-m-d')
            : Carbon::parse($fileRecord->created_at)->format('Y-m-d');

        if ($fileRecord->parent_folder == 'root') {
            $relativePath = "common/{$dateFolder}/{$fileRecord->segment}/{$fileRecord->name}";
        } else {
            $relativePath = "common/{$dateFolder}/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";
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
                throw new \Exception("Failed to download archive common file: {$fileRecord->name}");
            }

            Log::info("Common archive download complete: $finalPath");
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
            Log::info("Common: Skipping download, local file is up to date: {$fileRecord->name}");
            return;
        }

        if (!$this->authToken) {
            throw new \Exception("Missing auth token for NSE Common API download.");
        }

        $finalPath = $nseService->downloadFileFromApi(
            $this->authToken,
            $fileRecord->segment,
            $folderParam,
            $fileRecord->name,
            $absolutePath
        );

        if (!$finalPath) {
            throw new \Exception("Failed to download common file from NSE API: {$fileRecord->name}");
        }

        /*
        |--------------------------------------------------------------------------
        | Update DB Flags
        |--------------------------------------------------------------------------
        */
        $fileRecord->update([
            'is_downloaded'     => 1,
            'download_attempts' => 0  // ✅ was incorrectly set to 1 in original
        ]);

        // ✅ touch() uses $finalPath (.csv) not $absolutePath (.csv.gz which is now deleted)
        if ($fileRecord->nse_modified_at) {
            touch($finalPath, $fileRecord->nse_modified_at->timestamp);
        }

        Log::info("Common today download complete: $finalPath");
    }
}
