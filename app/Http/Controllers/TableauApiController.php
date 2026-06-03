<?php

namespace App\Http\Controllers;

use App\Services\TableauApiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TableauApiController extends Controller
{
    protected TableauApiService $tableau;

    public function __construct(TableauApiService $tableau)
    {
        $this->tableau = $tableau;
    }

}