<?php

namespace App\Console\Commands;

use App\Jobs\SyncNseCommaonFolders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NSECommonSegment extends Command
{
    protected $signature = 'run:nsecommon';

    protected $description = 'Sync NSE common data for segments CM, CD, CO, FO';

    public function handle()
    {
        
        $nseCronEnabled = config('constants.nse.cron_enabled');

        if ( !$nseCronEnabled ) {
            Log::channel('syncron')->info('NSE Common cron disabled');
            return Command::FAILURE;
        }

        Log::channel('syncron')->info('NSE Common cron started----'. now('Asia/Kolkata'));

        $segments = ['CM', 'CO', 'CD', 'FO'];

        foreach ($segments as $segment) {
            SyncNseCommaonFolders::dispatch(
                $segment,
                ''
            )->onQueue('nse-cron');
        }

        Log::channel('syncron')->info('NSE Common jobs dispatched----'.  now('Asia/Kolkata'));

        $this->info('NSE Common sync dispatched successfully----'.  now('Asia/Kolkata'));

        return Command::SUCCESS;
    }
}
