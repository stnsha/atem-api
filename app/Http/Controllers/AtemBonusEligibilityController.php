<?php

namespace App\Http\Controllers;

use App\Models\AtemBonusEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

}
