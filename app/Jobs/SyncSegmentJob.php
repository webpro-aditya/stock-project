<?php

namespace App\Jobs;

use App\Services\NSEService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncSegmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries   = 3;

    public function __construct(public string $segment) {}

    public function handle(NSEService $service)
    {
        try {

            Log::info("SyncSegmentJob started", [
                'segment' => $this->segment
            ]);

            $token = $service->getAuthToken();

            if (!$token) {
                Log::error("NSE token generation failed");
                return;
            }

            $folders = [];

            // 🔥 Include root folder FIRST
            $folders[] = '';

            $this->collectFolders(
                $service,
                $token,
                $this->segment,
                '',
                $folders
            );

            if (empty($folders)) {
                Log::warning("No folders found for segment");
                return;
            }

            $jobs = collect($folders)
                ->map(fn ($path) =>
                    new ProcessFolderJob(
                        $this->segment,
                        $path
                    )
                )
                ->values()
                ->toArray();

            // 🔥 Sequential execution
            Bus::chain($jobs)->dispatch();

            Log::info("Folder chain dispatched", [
                'total_folders' => count($jobs)
            ]);

        } catch (\Throwable $e) {

            Log::error("SyncSegmentJob failed", [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
            ]);

            throw $e;
        }
    }

    private function collectFolders(
        NSEService $service,
        string $token,
        string $segment,
        string $path,
        array &$result
    ) {

        $response = $service->getFolderFilesList(
            $token,
            $segment,
            $path
        );

        if (empty($response['data']) || !is_array($response['data'])) {
            return;
        }

        foreach ($response['data'] as $item) {

            if (($item['type'] ?? null) === 'Folder') {

                $next = $path
                    ? "$path/{$item['name']}"
                    : $item['name'];

                $result[] = $next;

                // 🔥 Recursive traversal
                $this->collectFolders(
                    $service,
                    $token,
                    $segment,
                    $next,
                    $result
                );
            }
        }
    }
}