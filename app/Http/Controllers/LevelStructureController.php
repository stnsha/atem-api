<?php

namespace App\Http\Controllers;

use App\Models\LevelStructure;
use Illuminate\Http\JsonResponse;

class LevelStructureController extends Controller
{
    /**
     * GET /api/atem/levels
     */
    public function index(): JsonResponse
    {
        $levels = LevelStructure::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $levels,
        ]);
    }
}
