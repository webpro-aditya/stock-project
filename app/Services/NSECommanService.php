<?php


namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Exception;

class NSECommanService
{
    private $nseCredentials;

    public function __construct()
    {
        $this->nseCredentials = config('constants.nse');
    }

    public function getAuthToken()
    {
        try {
            $testMode = $this->nseCredentials['test_mode'] ?? false;
            $encryptedPassword = encryptPassword($this->nseCredentials['password'], $this->nseCredentials['secret']);

            if (!$testMode) {

                $payload = json_encode([
                    "memberCode" => $this->nseCredentials['member_code'],
                    "loginId"    => $this->nseCredentials['login_id'],
                    "password"   => $encryptedPassword
                ]);

                $ch = curl_init($this->nseCredentials['base_url'] . "/login/" . $this->nseCredentials['version']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

                $response = curl_exec($ch);
                $data = json_decode($response, true);
                curl_close($ch);
                Log::info('NSE Login Token Generated: ' . $response);
            } else {
                $data = demoResponse();
                Log::info('NSE Test Login Token Generated: ' . $data['token']);
            }

            if (isset($data['status']) && $data['status'] === 'success') {
                Session::put('nse_auth_token', [
                    'value' => $data['token'],
                    'expires_at' => Carbon::now()->addMinutes(60)->timestamp
                ]);
                return $data['token'];
            } else {
                $errorCode = $data['responseCode'][0] ?? 'Unknown';
                Log::info("Login Failed. NSE Error Code: " . $errorCode . ". Check documentation for details.");
            }
        } catch (Exception $e) {
            Log::info("Error NSE during authentication: " . $e->getMessage());
        }
    }

    public function getFolderFilesList($authToken, $segment, $folder)
    {
        $creds = $this->nseCredentials;

        $folder = $this->normalizeFolder($folder);

        $query = http_build_query(
            [
                'segment'    => $segment,
                'folderPath' => $folder
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $url = "{$creds['base_url']}/common/content/{$creds['version']}?$query";

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            Log::error('INVALID URL GENERATED', [
                'folder' => $folder,
                'url' => $url
            ]);
        }


        $headers = [
            'Authorization: Bearer ' . $authToken,
            'Accept: application/json',
            'User-Agent: Mozilla/5.0'
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true, // turn OFF only locally
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        $info     = curl_getinfo($curl);

        curl_close($curl);

        if ($err) {
            Log::error("NSE cURL Error: " . $err);
            return ['status' => 'error', 'message' => $err];
        }

        if ($info['http_code'] !== 200) {

            Log::warning("NSE API non-200 response", [
                'code' => $info['http_code'],
                'body' => $response
            ]);
        }

        return json_decode($response, true);
    }

   private function normalizeFolder(?string $folder): string
{
    if (!$folder) {
        return '';
    }

    // remove invisible unicode characters
    $folder = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $folder);

    // collapse multiple spaces
    $folder = preg_replace('/\s+/', ' ', $folder);

    $folder = trim($folder);

    if (strtolower($folder) === 'root') {
        return '';
    }

    return trim($folder, '/');
}
public function downloadFileFromApi($authToken, $segment, $folder, $fileName, $savePath)
    {
        $creds = $this->nseCredentials;

        $queryParams = http_build_query([
            'segment'    => $segment,
            'folderPath' => '/' . $folder,
            'filename'   => $fileName
        ], '', '&', PHP_QUERY_RFC3986);

        $url = "{$creds['base_url']}/common/file/download/{$creds['version']}?{$queryParams}";

        $cookieString = 'HttpOnly';
        if (isset($creds['cookie_abck'])) $cookieString .= '; _abck=' . $creds['cookie_abck'];
        if (isset($creds['cookie_bm_sz'])) $cookieString .= '; bm_sz=' . $creds['cookie_bm_sz'];

        $fp = fopen($savePath, 'w+');
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $authToken,
                'Cookie: ' . $cookieString
            ),
        ));

        curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        curl_close($curl);
        fclose($fp);

        if ($err) {
            Log::error("NSE cURL Error: " . $err);
            if (file_exists($savePath)) unlink($savePath);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300 && file_exists($savePath) && filesize($savePath) > 0) {

            $processingPath = $savePath;

            if (str_ends_with($processingPath, '.gz')) {
                $processingPath = $this->decompressGzFile($processingPath);
            }

            $extension = strtolower(pathinfo($processingPath, PATHINFO_EXTENSION));

            if (in_array($extension, ['lis', 'txt', 'csv', 'dat'])) {
                $this->convertPipeToCsv($processingPath);
            }

            return true;
        }

        if (file_exists($savePath)) unlink($savePath);
        Log::error("NSE Download Failed [HTTP $httpCode]");
        return false;
    }

     private function decompressGzFile($filePath)
    {
        $bufferSize = 4096;
        // New filename is the original path MINUS '.gz'
        $outFileName = str_replace('.gz', '', $filePath);

        $file = gzopen($filePath, 'rb');
        if (!$file) return $filePath; // Return original if failed

        $outFile = fopen($outFileName, 'wb');

        while (!gzeof($file)) {
            fwrite($outFile, gzread($file, $bufferSize));
        }

        fclose($outFile);
        gzclose($file);

        // Delete the original .gz file
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Return the new clean filename (e.g., trade.txt)
        return $outFileName;
    }

    private function convertPipeToCsv($filePath)
    {
        $inputHandle = fopen($filePath, 'r');
        if ($inputHandle === false) return;

        $tempPath = $filePath . '.tmp';
        $outputHandle = fopen($tempPath, 'w');

        // Read Pipe (|), Write Comma (Standard CSV)
        while (($data = fgetcsv($inputHandle, 0, '|')) !== false) {
            // Filter empty rows if necessary
            if (array_filter($data)) {
                fputcsv($outputHandle, $data);
            }
        }

        fclose($inputHandle);
        fclose($outputHandle);

        // Swap the temp file with the original
        rename($tempPath, $filePath);
    }
}
