<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NSEDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NSEDownloadController extends Controller
{
    public function index()
    {
        $downloads = NSEDownload::select('id', 'url')->get();
        return view('admin.nse_downloads.index', ['downloads' => $downloads]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'urls'   => 'required|array|min:1',
            'urls.*' => 'required|url',
        ]);

        try {
            $urls = $request->input('urls');
            $insertData = [];
            $now = now();

            foreach ($urls as $url) {
                $cleanUrl = trim($url);

                if (!empty($cleanUrl)) {
                    $insertData[] = [
                        'url'        => $cleanUrl
                    ];
                }
            }

            NSEDownload::truncate();
            
            if (!empty($insertData)) {
                NSEDownload::insert($insertData);
            }

            return response()->json([
                'success' => true,
                'message' => count($insertData) . ' URL(s) saved successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save NSE download URLs: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'A database error occurred while saving the URLs.'
            ], 500);
        }
    }
}
