<?php

namespace App\Http\Controllers;

use App\Models\AtemStatus;
use Illuminate\Http\JsonResponse;

class AtemStatusController extends Controller
{
    /**
     * GET /api/atem/statuses
     */
    public function index(): JsonResponse
    {
        $statuses = AtemStatus::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $statuses,
        ]);
    }
}
