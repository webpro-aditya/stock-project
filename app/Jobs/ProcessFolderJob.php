
<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use App\Services\NSEService;

class ProcessFolderJob implements ShouldQueue
{
    public $timeout = 1800;

    public function __construct(
        public string $segment,
        public string $folder
    ) {}

    public function handle(NSEService $service)
    {
        $token = $service->getAuthToken();

        $response = $service->getFolderFilesList(
            $token,
            $this->segment,
            $this->folder
        );

        if (empty($response['data'])) return;

        foreach ($response['data'] as $item) {

            if ($item['type'] !== 'File') continue;

            $relative = "nse/{$this->segment}/{$this->folder}/{$item['name']}";

            Storage::makeDirectory(dirname($relative));

            $service->downloadFile(
                $token,
                $this->segment,
                $this->folder,
                $item['name'],
                Storage::path($relative)
            );
        }
    }
}