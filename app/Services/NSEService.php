<?php


namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
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

        $folder = ($folder == 'Root') ? '' : $folder;

        $queryParams = http_build_query([
            'segment'    => $segment,
            'folderPath' => '/' . $folder
        ]);

        $url = "{$creds['base_url']}/member/content/{$creds['version']}?" . urldecode($queryParams);
        
        Log::info("NSE API Request: " . $url);

        $headers = [
            'Authorization: Bearer ' . $authToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => $headers,
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
            Log::warning("NSE API non-200 response: " . $info['http_code'], ['body' => $response]);
        }

        return json_decode($response, true);
    }

    public function downloadFileFromApi($authToken, $segment, $folder, $fileName, $savePath)
    {
        $creds = $this->nseCredentials;

        $queryParams = http_build_query([
            'segment'    => $segment,
            'folderPath' => '/' . $folder,
            'filename'   => $fileName
        ]);

        $url = "{$creds['base_url']}/member/file/download/{$creds['version']}?{$queryParams}";

        $cookieString = 'HttpOnly';
        if (isset($creds['cookie_abck'])) {
            $cookieString .= '; _abck=' . $creds['cookie_abck'];
        }
        if (isset($creds['cookie_bm_sz'])) {
            $cookieString .= '; bm_sz=' . $creds['cookie_bm_sz'];
        }

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
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $authToken,
                'Cookie: ' . $cookieString
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        curl_close($curl);
        fclose($fp);

        if ($err) {
            Log::error("NSE cURL Error: " . $err);
            return false;
        }

        // ... inside downloadFileFromApi ...

        // 5. Validation: Check HTTP 200 and File Size
        if ($httpCode >= 200 && $httpCode < 300 && file_exists($savePath) && filesize($savePath) > 0) {

            // CHECK 1: Is it a GZIP file? (.gz)
            if (str_ends_with($fileName, '.gz')) {
                $response = $this->decompressGzFile($savePath);
            }

            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            if (in_array($extension, ['lis', 'txt', 'csv'])) {
                $response = $this->convertPipeToCsv($savePath);
            }

            return $response;
        }

        Log::error("NSE Download Failed [HTTP $httpCode]: " . file_get_contents($savePath));

        return false;
    }

    private function decompressGzFile($filePath)
    {
        $bufferSize = 4096; // Read in 4KB chunks
        $outFileName = str_replace('.gz', '', $filePath);

        // Open the GZ file for reading (binary safe)
        $file = gzopen($filePath, 'rb');
        if (!$file) return false;

        // Open the output file for writing
        $outFile = fopen($outFileName, 'wb');

        // Stream the uncompressed data
        while (!gzeof($file)) {
            fwrite($outFile, gzread($file, $bufferSize));
        }

        // Close both
        fclose($outFile);
        gzclose($file);

        // Delete the original .gz file and keep the unzipped version
        unlink($filePath);
        rename($outFileName, $filePath); // Rename it back to original name if you want, or handle extension logic

        return true;
    }

    private function convertPipeToCsv($filePath)
    {
        // 1. Open the downloaded file for reading
        $inputHandle = fopen($filePath, 'r');
        if ($inputHandle === false) return;

        // 2. Create a temporary file for writing
        $tempPath = $filePath . '.tmp';
        $outputHandle = fopen($tempPath, 'w');

        // 3. Read pipe-delimited, write comma-delimited
        while (($data = fgetcsv($inputHandle, 0, '|')) !== false) {
            // fputcsv automatically handles quoting if data contains commas
            fputcsv($outputHandle, $data);
        }

        // 4. Close handles
        fclose($inputHandle);
        fclose($outputHandle);

        // 5. Replace the original file with the new CSV
        rename($tempPath, $filePath);
    }
}
