<?php

namespace App\Http\Controllers;

use App\Models\IncentiveRule;
use Illuminate\Http\JsonResponse;

class IncentiveRuleController extends Controller
{
    /**
     * GET /api/atem/rules
     */
    public function index(): JsonResponse
    {
        $rules = IncentiveRule::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data'    => $rules,
        ]);
    }
}
