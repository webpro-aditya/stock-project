<?php

namespace App\Jobs;

use App\Models\NseContent;
use App\Services\NSEService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SyncSegmentDownloadJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries   = 1;

    public function __construct(public string $segment) {}

    public function uniqueId()
    {
        return $this->segment;
    }

    public function handle(NSEService $service)
    {
        Log::info("Segment download job started", [
            'segment' => $this->segment
        ]);

        $token = $service->getAuthToken();

        if (!$token) {
            Log::error("NSE token generation failed");
            saveSyncLog('common', $this->segment, '400', '803', "NSE token generation failed");
            return;
        }

        $today = Carbon::today()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Fetch Only:
        | - Files
        | - Modified today
        | - Not yet downloaded
        |--------------------------------------------------------------------------
        */
        NseContent::where('segment', $this->segment)
            ->where('type', 'File')
            ->whereDate('nse_modified_at', $today)
            ->where(function ($q) {
                $q->whereNull('is_downloaded')
                  ->orWhere('is_downloaded', 0);
            })
            ->orderBy('id')
            ->chunkById(100, function ($files) use ($service, &$token) {

                foreach ($files as $file) {

                    /*
                    |--------------------------------------------------------------------------
                    | Stop After 3 Failures
                    |--------------------------------------------------------------------------
                    */
                    if ($file->download_attempts >= 3) {

                        Log::warning("Deleting file after 3 failed attempts", [
                            'file' => $file->name
                        ]);

                        $file->delete();
                        continue;
                    }

                    $dateFolder = Carbon::parse($file->created_at)
                        ->format('Y-m-d');

                    $relativePath = "nse/{$dateFolder}/{$file->segment}/{$file->parent_folder}/{$file->name}";
                    $absolutePath = Storage::path($relativePath);

                    $directory = dirname($relativePath);

                    if (!Storage::exists($directory)) {
                        Storage::makeDirectory($directory);
                    }

                    $folderParam = (
                        $file->parent_folder === 'root' ||
                        $file->parent_folder === '' ||
                        strtolower($file->parent_folder) === 'root'
                    ) ? '' : $file->parent_folder;

                    try {

                        $success = $service->downloadFileFromApi(
                            $token,
                            $file->segment,
                            $folderParam,
                            $file->name,
                            $absolutePath
                        );

                        if (!$success) {
                            throw new \Exception("Download returned false");
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | SUCCESS
                        |--------------------------------------------------------------------------
                        */
                        if (Storage::exists($relativePath)) {
                            $file->update([
                                'is_downloaded'     => 1,
                                'download_attempts' => 1
                            ]);
                            continue;
                        }

                        if ($file->nse_modified_at) {
                            touch($absolutePath, $file->nse_modified_at->timestamp);
                        }

                        Log::info("Downloaded successfully", [
                            'file' => $file->name
                        ]);

                    } catch (\Throwable $e) {

                        $file->increment('download_attempts');

                        Log::error("Download failed", [
                            'file'    => $file->name,
                            'attempt' => $file->download_attempts,
                            'error'   => $e->getMessage()
                        ]);

                        saveSyncLog('common', $this->segment, '400', '', 'Download failed' . $file->name .'Attempt: '.  $file->download_attempts .'Error: '. $e->getMessage());

                        if ($file->fresh()->download_attempts >= 3) {

                            Log::warning("Deleting after 3 failures", [
                                'file' => $file->name
                            ]);

                            $file->delete();
                        }
                    }
                }
            });

        Log::info("Segment download job completed", [
            'segment' => $this->segment
        ]);
    }
}