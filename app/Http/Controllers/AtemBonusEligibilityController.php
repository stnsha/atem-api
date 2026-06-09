<?php

namespace App\Http\Controllers;

use App\Models\AtemBonusEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AtemBonusEligibilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AtemBonusEligibility::query();

        if ($request->filled('month')) {
            $query->where('month', (int) $request->month);
        }
        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', (int) $request->staff_id);
        }

        $records = $query->orderBy('total_incentive', 'desc')->get();

        return response()->json(array('data' => $records));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = AtemBonusEligibility::findOrFail($id);

        $validated = $request->validate(array(
            'remark' => 'nullable|string|max:500',
        ));

        $record->update(array('remark' => $validated['remark']));

        return response()->json(array('data' => $record));
    }

    public function calculate(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->month);
        $year  = $request->input('year', now()->year);

        Artisan::call('atem:calculate-bonus', array(
            '--month' => (int) $month,
            '--year'  => (int) $year,
        ));

        $output = Artisan::output();

        return response()->json(array(
            'message' => 'Calculation complete.',
            'output'  => trim($output),
            'month'   => (int) $month,
            'year'    => (int) $year,
        ));
    }
}
