<?php

namespace App\Console\Commands;

use App\Jobs\SyncCommonSegmentDownloadJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NSECommonSegmentDownload extends Command
{
    protected $signature = 'run:commondownload';

    protected $description = 'Download NSE member files for segments CM, CD, CO, FO';

    public function handle()
    {
        $nseCronEnabled = config('constants.nse.cron_enabled');

        if ( !$nseCronEnabled ) {
            Log::channel('syncron')->info('NSE Common cron disabled');
            return Command::FAILURE;
        }
        
        Log::channel('syncron')->info('NSE Member cron started----'. now('Asia/Kolkata'));

        $segments = ['CM', 'CO', 'CD', 'FO'];

        foreach ($segments as $segment) {
            SyncCommonSegmentDownloadJob::dispatch(
                $segment
            );
        }

        Log::channel('syncron')->info('NSE Member jobs dispatched----'.  now('Asia/Kolkata'));

        $this->info('NSE Member sync dispatched successfully----'.  now('Asia/Kolkata'));

        return Command::SUCCESS;
    }
}
