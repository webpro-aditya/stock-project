<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NseContent;
use App\Models\SyncLog;
use App\Jobs\SyncNseFileJob;
use App\Jobs\SyncNseFolders;
use App\Services\NSEService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class NSELogController extends Controller
{
    public function index($type, $segment)
    {
        $segment = strtoupper($segment);

        $logs = SyncLog::where('type', $type)
            ->where('segment', $segment)
            ->orderByDesc('id')
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString();

        return view('admin.nse.logs.index',  [
            'logs'    => $logs,
            'type'    => $type,
            'segment' => $segment
        ]);
    }
}
