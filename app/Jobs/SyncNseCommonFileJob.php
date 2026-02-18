<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue; // Not used for sync, but good practice
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\NseCommanContent;
use App\Services\NSECommanService; // Ensure you import your service
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncNseCommonFileJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileId;
    protected $authToken;

    public function __construct($fileId, $authToken)
    {
        $this->fileId = $fileId;
        $this->authToken = $authToken;
    }

    public function handle(NSECommanService $nseService)
    {
        $fileRecord = NseCommanContent::findOrFail($this->fileId);

        $relativePath = "nse_cache/{$fileRecord->segment}/{$fileRecord->parent_folder}/{$fileRecord->name}";
        $absolutePath = Storage::path($relativePath);

        $shouldDownload = true;
        if ($shouldDownload) {
            $directory = dirname($relativePath);
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            $folderParam = ($fileRecord->parent_folder === 'root' || $fileRecord->parent_folder === 'Root' || $fileRecord->parent_folder === '') ? '' : $fileRecord->parent_folder;
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

            if ($fileRecord->nse_modified_at) {
                touch($absolutePath, $fileRecord->nse_modified_at->timestamp);
            }
        }
    }
}