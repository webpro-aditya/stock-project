<?php


namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Exception;

class NSEService
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

        $url = "{$creds['base_url']}/member/content/{$creds['version']}?$query";

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            Log::error('INVALID URL GENERATED', [
                'folder' => $folder,
                'url' => $url
            ]);
            saveSyncLog('member', $segment, '400', '', 'INVALID URL GENERATED. Folder: ' . $folder . ', URL: ' . $url);
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
            saveSyncLog('member', $segment, '400', '', 'NSE cURL Error: ' . $err);
            return ['status' => 'error', 'message' => $err];
        }

        if ($info['http_code'] !== 200) {
            Session::put('member_api_success', false);
            Log::warning("NSE API non-200 response", [
                'code' => $info['http_code'],
                'body' => $response
            ]);
            saveSyncLog('member', $segment, $info['http_code'], '', 'NSE API non-200 response' . $err);
        } else {
            Session::put('member_api_success', true);
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


    function normalizeUnderscoreTitle($string)
    {
        return str_replace(
            ' ',
            '_',
            Str::title(str_replace('_', ' ', $string))
        );
    }

    public function downloadFileFromApi($authToken, $segment, $folder, $fileName, $savePath)
    {
        $creds = $this->nseCredentials;

        $queryParams = http_build_query([
            'segment'    => $segment,
            'folderPath' => '/' . $folder,
            'filename'   => $fileName
        ], '', '&', PHP_QUERY_RFC3986);

        $url = "{$creds['base_url']}/member/file/download/{$creds['version']}?{$queryParams}";

        $cookieString = 'HttpOnly';
        if (isset($creds['cookie_abck'])) $cookieString .= '; _abck=' . $creds['cookie_abck'];
        if (isset($creds['cookie_bm_sz'])) $cookieString .= '; bm_sz=' . $creds['cookie_bm_sz'];

        $fp   = fopen($savePath, 'wb+');  // ✅ binary mode
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $url,
            CURLOPT_FILE           => $fp,
            // ✅ CURLOPT_ENCODING removed entirely
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $authToken,
                'Cookie: ' . $cookieString,
                'Accept-Encoding: identity',  // ✅ no HTTP compression
            ),
        ));

        curl_exec($curl);
        $httpCode    = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $err         = curl_error($curl);

        curl_close($curl);
        fclose($fp);  // ✅ closed before any file operations

        if ($err) {
            Log::error("NSE cURL Error: " . $err);
            saveSyncLog('member', $segment, '400', '', 'NSE cURL Error: ' . $err);
            if (file_exists($savePath)) unlink($savePath);
            return false;
        }

        // ✅ Guard against HTML error pages being saved as files
        if (!empty($contentType) && str_contains($contentType, 'text/html')) {
            Log::error("NSE returned HTML error page [HTTP $httpCode]");
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

            return $processingPath;  // ✅ return final path, not just true
        }

        if (file_exists($savePath)) unlink($savePath);
        Log::error("NSE Download Failed [HTTP $httpCode]");
        saveSyncLog('member', $segment, $httpCode, '', "NSE Download Failed [HTTP $httpCode]");
        return false;
    }


    private function decompressGzFile($filePath)
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            Log::error("GZ Decompress: File missing or empty: $filePath");
            return $filePath;
        }

        // ✅ Validate magic bytes before attempting decompress
        $handle = fopen($filePath, 'rb');
        $magic  = fread($handle, 2);
        fclose($handle);

        if ($magic !== "\x1f\x8b") {
            Log::error("GZ Decompress: Not a valid gzip file. Magic: " . bin2hex($magic) . " | $filePath");
            return $filePath;
        }

        $outFileName = str_replace('.gz', '', $filePath);

        $file = gzopen($filePath, 'rb');
        if (!$file) {
            Log::error("GZ Decompress: gzopen() failed for: $filePath");
            return $filePath;
        }

        $outFile = fopen($outFileName, 'wb');
        if (!$outFile) {
            Log::error("GZ Decompress: Cannot create output file: $outFileName");
            gzclose($file);
            return $filePath;
        }

        $bufferSize = 65536;  // ✅ 64KB buffer, much faster than 4096
        while (!gzeof($file)) {
            $chunk = gzread($file, $bufferSize);
            if ($chunk === false) {
                Log::error("GZ Decompress: gzread() failed mid-stream");
                break;
            }
            fwrite($outFile, $chunk);
        }

        fclose($outFile);
        gzclose($file);

        if (!file_exists($outFileName) || filesize($outFileName) === 0) {
            Log::error("GZ Decompress: Output file empty or missing: $outFileName");
            return $filePath;
        }

        unlink($filePath);
        return $outFileName;
    }


    private function convertPipeToCsv($filePath)
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            Log::error("convertPipeToCsv: File missing or empty: $filePath");
            return;
        }

        // ✅ Peek first line — skip if not pipe-delimited
        $peek = fopen($filePath, 'r');
        if (!$peek) {
            Log::error("convertPipeToCsv: Cannot open file: $filePath");
            return;
        }
        $firstLine = fgets($peek);
        fclose($peek);

        if ($firstLine === false || strpos($firstLine, '|') === false) {
            Log::info("convertPipeToCsv: No pipes found, skipping: $filePath");
            return;
        }

        $inputHandle  = fopen($filePath, 'r');
        $tempPath     = $filePath . '.tmp';
        $outputHandle = fopen($tempPath, 'w');

        if (!$inputHandle || !$outputHandle) {
            Log::error("convertPipeToCsv: Failed to open file handles for: $filePath");
            return;
        }

        $lineCount = 0;
        while (($line = fgets($inputHandle)) !== false) {
            $line   = rtrim($line, "\r\n");  // ✅ only strip line endings
            $fields = explode('|', $line);

            // ✅ Proper RFC 4180 CSV quoting
            $fields = array_map(function ($field) {
                $field = str_replace('"', '""', $field);
                if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                    $field = '"' . $field . '"';
                }
                return $field;
            }, $fields);

            fwrite($outputHandle, implode(',', $fields) . "\n");
            $lineCount++;
        }

        fclose($inputHandle);
        fclose($outputHandle);

        if (!rename($tempPath, $filePath)) {
            Log::error("convertPipeToCsv: Failed to rename temp to: $filePath");
            if (file_exists($tempPath)) unlink($tempPath);
            return;
        }

        Log::info("convertPipeToCsv: Converted $lineCount lines in $filePath");
    }
}
