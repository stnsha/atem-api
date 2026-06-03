<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemStatus;
use App\Models\IncentiveRule;
use App\Models\LevelStructure;
use App\Services\IncentiveCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemController extends Controller
{
    private IncentiveCalculatorService $calculator;

    public function __construct(IncentiveCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * GET /api/atem/lookups
     * Returns levels, rules and statuses in a single call.
     */
    public function lookups(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'levels'   => LevelStructure::orderBy('id')->get(),
                'rules'    => IncentiveRule::orderBy('id')->get(),
                'statuses' => AtemStatus::orderBy('id')->get(),
            ],
        ]);
    }

    /**
     * POST /api/atem
     * Creates a draft card so ARCI members can be attached before final save.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'issuer_staff_id' => 'nullable|integer',
            'issuer_name'     => 'nullable|string|max:255',
            'department_id'   => 'nullable|integer',
            'department_name' => 'nullable|string|max:255',
            'title'           => 'nullable|string|max:255',
        ]);

        $atem = Atem::create([
            'title'           => $data['title'] ?? 'Untitled ATEM',
            'issuer_staff_id' => $data['issuer_staff_id'] ?? null,
            'issuer_name'     => $data['issuer_name'] ?? null,
            'department_id'   => $data['department_id'] ?? null,
            'department_name' => $data['department_name'] ?? null,
            'record_state'    => 'draft',
            'created_by'      => $data['issuer_staff_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['id' => $atem->id],
        ]);
    }

    /**
     * GET /api/atem/{id}
     */
    public function show(int $id): JsonResponse
    {
        $atem = Atem::with('arci')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $atem,
        ]);
    }

    /**
     * PUT /api/atem/{id}
     * Persists the full card, recomputing timeline and incentive server-side.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'google_link'        => 'nullable|string|max:255',
            'level_structure_id' => 'nullable|integer|exists:level_structures,id',
            'incentive_rule_id'  => 'nullable|integer|exists:incentive_rules,id',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'is_extended'        => 'boolean',
            'extended_date_1'    => 'nullable|date',
            'extended_date_2'    => 'nullable|date',
            'atem_status_id'     => 'nullable|integer|exists:atem_statuses,id',
            'failure_reason'     => 'nullable|string',
            'excellence_remark'  => 'nullable|string',
            'remarks'            => 'nullable|string',
            'updated_by'         => 'nullable|integer',
            'finalize'           => 'boolean',
        ]);

        $level  = !empty($data['level_structure_id']) ? LevelStructure::find($data['level_structure_id']) : null;
        $rule   = !empty($data['incentive_rule_id']) ? IncentiveRule::find($data['incentive_rule_id']) : null;
        $status = !empty($data['atem_status_id']) ? AtemStatus::find($data['atem_status_id']) : null;
        $statusValue = $status ? $status->value : null;

        // Extension handling: count is derived from the extended dates supplied,
        // capped at the business maximum of two.
        $isExtended = !empty($data['is_extended']);
        $ext1 = $isExtended ? ($data['extended_date_1'] ?? null) : null;
        $ext2 = $isExtended ? ($data['extended_date_2'] ?? null) : null;
        $extensionCount = 0;
        if ($ext1) {
            $extensionCount++;
        }
        if ($ext2) {
            $extensionCount++;
        }
        if ($extensionCount > 2) {
            $extensionCount = 2;
        }

        // Final due date follows the latest extended date, otherwise the end date.
        $finalDue = $data['end_date'] ?? null;
        if ($ext1) {
            $finalDue = $ext1;
        }
        if ($ext2) {
            $finalDue = $ext2;
        }

        // Closure date is set automatically when a closing status is selected.
        $closingStatuses = ['Completed', 'Completed with Excellence', 'Failed'];
        $closureDate = $atem->closure_date;
        $closedBy = $atem->closed_by;
        if ($statusValue !== null && in_array($statusValue, $closingStatuses, true)) {
            if (empty($closureDate)) {
                $closureDate = now()->toDateString();
            }
            $closedBy = $data['updated_by'] ?? $closedBy;
        }

        $incentive = $this->calculator->calculate($level, $rule, $statusValue);

        $atem->fill([
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
            'google_link'            => $data['google_link'] ?? null,
            'level_structure_id'     => $data['level_structure_id'] ?? null,
            'incentive_rule_id'      => $data['incentive_rule_id'] ?? null,
            'base_incentive'         => $incentive['base'],
            'start_date'             => $data['start_date'] ?? null,
            'end_date'               => $data['end_date'] ?? null,
            'is_extended'            => $isExtended,
            'extended_date_1'        => $ext1,
            'extended_date_2'        => $ext2,
            'extension_count'        => $extensionCount,
            'final_due_date'         => $finalDue,
            'closure_date'           => $closureDate,
            'atem_status_id'         => $data['atem_status_id'] ?? null,
            'failure_reason'         => $data['failure_reason'] ?? null,
            'excellence_remark'      => $data['excellence_remark'] ?? null,
            'remarks'                => $data['remarks'] ?? null,
            'a_incentive_amount'     => $incentive['a'],
            'r_incentive_amount'     => $incentive['r'],
            'total_incentive_amount' => $incentive['total'],
            'claimable'              => $incentive['claimable'],
            'updated_by'             => $data['updated_by'] ?? null,
            'closed_by'              => $closedBy,
        ]);

        if (!empty($data['finalize'])) {
            $atem->record_state = 'active';
        }

        $atem->save();

        return response()->json([
            'success' => true,
            'data'    => $atem->fresh('arci'),
        ]);
    }
}
