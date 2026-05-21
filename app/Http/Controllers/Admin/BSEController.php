<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BSEService;
use Illuminate\Http\Request;

class BSEController extends Controller
{
    private $bSEService;

    public function __construct(BSEService $bSEService)
    {
        $this->bSEService = $bSEService;
    }

    public function index()
    {
        return $this->bSEService->login();
    }
}
