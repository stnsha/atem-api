<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemStatus;
use App\Models\IncentiveRule;
use App\Models\LevelStructure;
use App\Services\IncentiveCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    private const ALLOWED_ATTACHMENT_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    /**
     * POST /api/atem
     * Persists a whole ATEM card (fields + ARCI + reference links + attachments)
     * in one transaction. Nothing is written until the issuer saves on the
     * frontend. mode=final -> record_state 'created'; mode=draft -> 'draft'.
     * Attachments arrive as base64 and are stored in the same call so a save
     * never misses the files staged in the browser.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'description'            => 'nullable|string',
            'issuer_staff_id'        => 'nullable|integer',
            'staff_dept_id'          => 'nullable|integer',
            'level_structure_id'     => 'nullable|integer|exists:level_structures,id',
            'incentive_rule_id'      => 'nullable|integer|exists:incentive_rules,id',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date',
            'created_by'             => 'nullable|integer',
            'mode'                   => 'nullable|in:draft,final',
            'arci'                   => 'nullable|array',
            'arci.*.staff_id'        => 'required_with:arci|integer',
            'arci.*.staff_dept_id'   => 'nullable|integer',
            'arci.*.role'            => 'required_with:arci|in:A,R,C,I',
            'reference_links'        => 'nullable|array',
            'reference_links.*.name' => 'required_with:reference_links|string|max:255',
            'reference_links.*.url'  => 'required_with:reference_links|url|max:1000',
            'attachments'            => 'nullable|array',
            'attachments.*.name'     => 'required_with:attachments|string|max:255',
            'attachments.*.content'  => 'required_with:attachments|string',
            'attachments.*.type'     => 'nullable|string|max:255',
            'attachments.*.size'     => 'nullable|integer',
        ]);

        // Attachments are validated by extension. Content-sniffing (Laravel's
        // mimes rule) wrongly rejects valid zip-based Office files (docx/xlsx).
        if (!empty($data['attachments'])) {
            foreach ($data['attachments'] as $att) {
                $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, self::ALLOWED_ATTACHMENT_EXT, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File type not allowed: ' . $att['name'],
                    ], 422);
                }
            }
        }

        // Lifecycle is a status now: Save-as-draft -> Draft, normal Save -> Pending.
        $statusValue = (($data['mode'] ?? 'final') === 'draft') ? 'Draft' : 'Active';
        $statusId = AtemStatus::where('value', $statusValue)->value('id');
        $createdBy = $data['created_by'] ?? ($data['issuer_staff_id'] ?? null);

        $level = !empty($data['level_structure_id']) ? LevelStructure::find($data['level_structure_id']) : null;
        $rule  = !empty($data['incentive_rule_id']) ? IncentiveRule::find($data['incentive_rule_id']) : null;
        $rCount = 0;
        if (!empty($data['arci'])) {
            foreach ($data['arci'] as $member) {
                if (isset($member['role']) && $member['role'] === 'R') {
                    $rCount++;
                }
            }
        }
        $incentive = $this->calculator->calculate($level, $rule, null, $rCount);

        $atem = DB::transaction(function () use ($data, $statusId, $createdBy, $incentive) {
            $atem = Atem::create([
                'title'                  => (isset($data['title']) && $data['title'] !== '') ? $data['title'] : 'Untitled ATEM',
                'description'            => $data['description'] ?? null,
                'issuer_staff_id'        => $data['issuer_staff_id'] ?? null,
                'staff_dept_id'          => $data['staff_dept_id'] ?? null,
                'level_structure_id'     => $data['level_structure_id'] ?? null,
                'incentive_rule_id'      => $data['incentive_rule_id'] ?? null,
                'atem_status_id'         => $statusId,
                'base_incentive'         => $incentive['base'],
                'start_date'             => $data['start_date'] ?? null,
                'end_date'               => $data['end_date'] ?? null,
                'final_due_date'         => $data['end_date'] ?? null,
                'closure_date'           => $data['end_date'] ?? null,
                'a_incentive_amount'     => $incentive['a'],
                'r_incentive_amount'     => $incentive['r'],
                'total_incentive_amount' => $incentive['total'],
                'claimable'              => $incentive['claimable'],
                'created_by'             => $createdBy,
            ]);

            if (!empty($data['arci'])) {
                foreach ($data['arci'] as $member) {
                    $atem->arci()->create([
                        'staff_id'      => $member['staff_id'],
                        'staff_dept_id' => $member['staff_dept_id'] ?? null,
                        'role'          => $member['role'],
                        'assigned_by'   => $createdBy,
                    ]);
                }
            }

            if (!empty($data['reference_links'])) {
                foreach ($data['reference_links'] as $link) {
                    $atem->referenceLinks()->create([
                        'name'     => $link['name'],
                        'url'      => $link['url'],
                        'added_by' => $createdBy,
                    ]);
                }
            }

            if (!empty($data['attachments'])) {
                foreach ($data['attachments'] as $att) {
                    $atem->attachments()->create([
                        'name'        => $att['name'],
                        'type'        => $att['type'] ?? null,
                        'size'        => $att['size'] ?? 0,
                        'content'     => $att['content'],
                        'uploaded_by' => $createdBy,
                    ]);
                }
            }

            return $atem;
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'id' => $atem->id,
            ],
        ]);
    }

    /**
     * GET /api/atem
     * Lists all ATEM cards (newest first) for the listing page. FK ids only -
     * issuer/department names are resolved on the odb frontend.
     */
    public function index(): JsonResponse
    {
        $atems = Atem::with(['levelStructure', 'incentiveRule', 'status', 'arci'])
            ->orderByDesc('id')
            ->get([
                'id', 'title', 'issuer_staff_id', 'staff_dept_id',
                'level_structure_id', 'incentive_rule_id', 'atem_status_id',
                'start_date', 'end_date', 'final_due_date',
                'total_incentive_amount', 'claimable', 'created_at',
            ]);

        return response()->json([
            'success' => true,
            'data'    => $atems,
        ]);
    }

    /**
     * GET /api/atem/{id}
     */
    public function show(int $id): JsonResponse
    {
        $atem = Atem::with([
            'arci',
            'referenceLinks',
            'attachments',
            'status',
            'progress',
            'auditLogs' => fn ($q) => $q->orderByDesc('created_at')->limit(100),
        ])->findOrFail($id);

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
            'level_structure_id' => 'nullable|integer|exists:level_structures,id',
            'incentive_rule_id'  => 'nullable|integer|exists:incentive_rules,id',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'is_extended'        => 'boolean',
            'extended_date_1'    => 'nullable|date',
            'extended_date_2'    => 'nullable|date',
            'atem_status_id'     => 'nullable|integer|exists:atem_statuses,id',
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

        // Closure date is the date the ATEM was actually closed (terminal status),
        // not the Final Due Date. Preserve it if already set on a re-save.
        $closingStatuses = ['Completed', 'Completed with Excellence', 'Failed'];
        $closedBy = $atem->closed_by;
        if ($statusValue !== null && in_array($statusValue, $closingStatuses, true)) {
            $closureDate = $atem->closure_date ?: now()->toDateString();
            $closedBy = $data['updated_by'] ?? $closedBy;
        } else {
            $closureDate = null;
        }

        $rCount = $atem->arci()->where('role', 'R')->count();
        $incentive = $this->calculator->calculate($level, $rule, $statusValue, $rCount);

        $atem->fill([
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
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
            'remarks'                => $data['remarks'] ?? null,
            'a_incentive_amount'     => $incentive['a'],
            'r_incentive_amount'     => $incentive['r'],
            'total_incentive_amount' => $incentive['total'],
            'claimable'              => $incentive['claimable'],
            'updated_by'             => $data['updated_by'] ?? null,
            'closed_by'              => $closedBy,
        ]);

        $atem->save();

        return response()->json([
            'success' => true,
            'data'    => $atem->fresh(['arci', 'referenceLinks', 'attachments', 'status']),
        ]);
    }

    /**
     * DELETE /api/atem/{id}
     * Permanently deletes a Draft ATEM. Only the Issuer may delete.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $atem = Atem::with('status')->findOrFail($id);

        if (!$atem->status || $atem->status->value !== 'Draft') {
            return response()->json([
                'success' => false,
                'message' => 'Only Draft ATEMs can be deleted.',
            ], 403);
        }

        $actorId = (int) $request->input('actor_id', 0);
        if ($actorId === 0 || $actorId !== (int) $atem->issuer_staff_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the Issuer can delete this ATEM.',
            ], 403);
        }

        $atem->arci()->delete();
        $atem->referenceLinks()->delete();
        $atem->attachments()->delete();
        $atem->progress()->delete();
        $atem->auditLogs()->delete();
        $atem->delete();

        return response()->json(['success' => true]);
    }
}
