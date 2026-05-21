<?php


namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;

class BSEService
{
    private $bseCredentials;
    private $sftp;

    public function __construct()
    {
        $this->bseCredentials = config('constants.bse');
    }

    public function login(): bool
    {
        $this->sftp = new SFTP($this->bseCredentials['hostname'], $this->bseCredentials['port']);

        if (!$this->sftp->login($this->bseCredentials['login_id'], $this->bseCredentials['password'])) {
            throw new \Exception(
                "Failed to authenticate BSE SFTP for user '{$this->bseCredentials['login_id']}' " .
                "at {$this->bseCredentials['hostname']}:{$this->bseCredentials['port']}"
            );
        }

        return true;
    }

}
