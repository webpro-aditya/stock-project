<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NseContent;
use App\Services\NSEService;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SyncNseFileJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileId;
    protected $authToken;
    protected $source; // today | archive

    public function __construct($fileId, $authToken = null, $source = 'today')
    {
        $this->fileId = $fileId;
        $this->authToken = $authToken;
        $this->source = $source;
    }

    public function handle(NSEService $nseService)
    {
        $fileRecord = NseContent::findOrFail($this->fileId);

        $dateFolder = Carbon::parse($fileRecord->created_at)->format('Y-m-d');

        $relativePath = "nse/{$dateFolder}/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";
        $absolutePath = Storage::path($relativePath);

        $fileExistsLocally = Storage::exists($relativePath);

        /*
    |--------------------------------------------------------------------------
    | ARCHIVE VIEW LOGIC
    |--------------------------------------------------------------------------
    | Only serve from local. Never hit API.
    */
        if ($this->source === 'archive') {

            if (!$fileExistsLocally) {
                throw new \Exception("Archived file not found in local storage.");
            }

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | TODAY VIEW LOGIC
    |--------------------------------------------------------------------------
    | Download if:
    |   - File does not exist
    |   - OR NSE modified date > local modified date
    */
        $shouldDownload = false;

        if (!$fileExistsLocally) {

            $shouldDownload = true;
        } else {

            $localModified = Carbon::createFromTimestamp(
                filemtime($absolutePath)
            );

            if (
                $fileRecord->nse_modified_at &&
                $fileRecord->nse_modified_at->gt($localModified)
            ) {
                $shouldDownload = true;
            }
        }

        if ($shouldDownload) {

            if (!$this->authToken) {
                throw new \Exception("Missing auth token for NSE API download.");
            }

            $directory = dirname($relativePath);

            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            $folderParam = (
                $fileRecord->parent_folder === 'root' ||
                $fileRecord->parent_folder === '' ||
                strtolower($fileRecord->parent_folder) === 'root'
            ) ? '' : $fileRecord->parent_folder;

            $success = $nseService->downloadFileFromApi(
                $this->authToken,
                $fileRecord->segment,
                $folderParam,
                $fileRecord->name,
                $absolutePath
            );

            if (!$success) {
                throw new \Exception("Failed to download file from NSE API.");
            }

            /*
        |--------------------------------------------------------------------------
        | Sync Local File Timestamp
        |--------------------------------------------------------------------------
        */
            if ($fileRecord->nse_modified_at) {
                touch($absolutePath, $fileRecord->nse_modified_at->timestamp);
            }
        }
    }
}
