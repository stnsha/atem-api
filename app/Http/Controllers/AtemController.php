<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemStatus;
use App\Models\IncentiveRule;
use App\Models\LevelStructure;
use App\Models\Pillar;
use App\Services\AtemAuditLogger;
use App\Services\IncentiveCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
     * Returns levels, rules, statuses and pillars in a single call.
     */
    public function lookups(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'levels'   => LevelStructure::orderBy('id')->get(),
                'rules'    => IncentiveRule::orderBy('id')->get(),
                'statuses' => AtemStatus::orderBy('id')->get(),
                'pillars'  => Pillar::orderBy('id')->get(),
            ],
        ]);
    }

    private const ALLOWED_ATTACHMENT_EXT = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    private function recalcBonusEligibility(Atem $atem): void
    {
        $months = array();

        if ($atem->start_date) {
            $sd = Carbon::parse($atem->start_date);
            $months[$sd->year . '-' . $sd->month] = array('month' => $sd->month, 'year' => $sd->year);
        }

        if ($atem->closure_date) {
            $cd = Carbon::parse($atem->closure_date);
            $months[$cd->year . '-' . $cd->month] = array('month' => $cd->month, 'year' => $cd->year);
        }

        foreach ($months as $entry) {
            Artisan::call('atem:calculate-bonus', array(
                '--month' => $entry['month'],
                '--year'  => $entry['year'],
            ));
        }
    }

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
            'atem_type'              => 'nullable|integer|in:1,2',
            'pillar_id'              => 'nullable|integer|exists:pillars,id',
            'reward_amount'          => 'nullable|numeric',
            'deduction_amount'       => 'nullable|numeric',
            'outlet_ids'             => 'nullable|array',
            'outlet_ids.*'           => 'integer',
            'area_manager_ids'       => 'nullable|array',
            'area_manager_ids.*'     => 'integer',
            'level_structure_id'     => 'nullable|integer|exists:level_structures,id',
            'incentive_rule_id'      => 'nullable|integer|exists:incentive_rules,id',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date',
            'created_by'             => 'nullable|integer',
            'mode'                   => 'nullable|in:draft,final',
            'arci'                        => 'nullable|array',
            'arci.*.staff_id'             => 'required_with:arci|integer',
            'arci.*.staff_dept_id'        => 'nullable|integer',
            'arci.*.outlet_id'            => 'nullable|integer',
            'arci.*.role'                 => 'required_with:arci|in:A,R,C,I',
            'arci.*.is_incentivised'      => 'nullable|boolean',
            'reference_links'        => 'nullable|array',
            'reference_links.*.name' => 'required_with:reference_links|string|max:255',
            'reference_links.*.url'  => 'required_with:reference_links|url|max:1000',
            'attachments'            => 'nullable|array',
            'attachments.*.name'     => 'required_with:attachments|string|max:255',
            'attachments.*.content'  => 'required_with:attachments|string',
            'attachments.*.type'     => 'nullable|string|max:255',
            'attachments.*.size'     => 'nullable|integer',
        ]);

        if (($data['mode'] ?? 'final') === 'final' && empty($data['reference_links'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one Reference Link is required.',
            ], 422);
        }

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
        $incentivisedACount = 0;
        $incentivisedRCount = 0;
        if (!empty($data['arci'])) {
            foreach ($data['arci'] as $member) {
                if (!empty($member['is_incentivised'])) {
                    if (isset($member['role']) && $member['role'] === 'A') { $incentivisedACount++; }
                    if (isset($member['role']) && $member['role'] === 'R') { $incentivisedRCount++; }
                }
            }
        }
        $incentive = $this->calculator->calculate($level, $rule, null, $incentivisedACount, $incentivisedRCount);

        $atem = DB::transaction(function () use ($data, $statusId, $createdBy, $incentive) {
            $atem = Atem::create([
                'title'                  => (isset($data['title']) && $data['title'] !== '') ? $data['title'] : 'Untitled ATEM',
                'description'            => $data['description'] ?? null,
                'issuer_staff_id'        => $data['issuer_staff_id'] ?? null,
                'staff_dept_id'          => $data['staff_dept_id'] ?? null,
                'atem_type'              => $data['atem_type'] ?? 1,
                'pillar_id'              => $data['pillar_id'] ?? null,
                'reward_amount'          => $data['reward_amount'] ?? null,
                'deduction_amount'       => $data['deduction_amount'] ?? null,
                'level_structure_id'     => $data['level_structure_id'] ?? null,
                'incentive_rule_id'      => $data['incentive_rule_id'] ?? null,
                'atem_status_id'         => $statusId,
                'base_incentive'         => $incentive['base'],
                'start_date'             => $data['start_date'] ?? null,
                'end_date'               => $data['end_date'] ?? null,
                'final_due_date'         => $data['end_date'] ?? null,
                'closure_date'           => null,
                'a_incentive_amount'     => $incentive['a'],
                'r_incentive_amount'     => $incentive['r'],
                'total_incentive_amount' => $incentive['total'],
                'claimable'              => $incentive['claimable'],
                'created_by'             => $createdBy,
            ]);

            if (!empty($data['arci'])) {
                foreach ($data['arci'] as $member) {
                    $atem->arci()->create([
                        'staff_id'        => $member['staff_id'],
                        'staff_dept_id'   => $member['staff_dept_id'] ?? null,
                        'outlet_id'       => $member['outlet_id'] ?? null,
                        'role'            => $member['role'],
                        'is_incentivised' => !empty($member['is_incentivised']),
                        'assigned_by'     => $createdBy,
                    ]);
                }
            }

            if (!empty($data['outlet_ids'])) {
                foreach (array_unique($data['outlet_ids']) as $outletId) {
                    $atem->outlets()->create(['outlet_id' => (int) $outletId]);
                }
            }

            if (!empty($data['area_manager_ids'])) {
                foreach (array_unique($data['area_manager_ids']) as $areaManagerId) {
                    $atem->areaManagers()->create(['staff_id' => (int) $areaManagerId]);
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

        $this->recalcBonusEligibility($atem);

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
    public function index(Request $request): JsonResponse
    {
        $includeDeleted = $request->query('include_deleted') == 1;

        $builder = $includeDeleted
            ? Atem::withTrashed()->with(['levelStructure', 'incentiveRule', 'status', 'arci', 'pillar', 'outlets', 'areaManagers'])
            : Atem::with(['levelStructure', 'incentiveRule', 'status', 'arci', 'pillar', 'outlets', 'areaManagers']);

        $query = $builder->orderByDesc('id');

        $staffId = $request->query('staff_id');
        if ($staffId) {
            $staffId = (int) $staffId;
            $query->where(function ($q) use ($staffId) {
                $q->where('issuer_staff_id', $staffId)
                  ->orWhereHas('arci', function ($q2) use ($staffId) {
                      $q2->where('staff_id', $staffId);
                  });
            });
        }

        $atems = $query->get([
            'id', 'title', 'issuer_staff_id', 'staff_dept_id',
            'atem_type', 'pillar_id',
            'level_structure_id', 'incentive_rule_id', 'atem_status_id',
            'start_date', 'end_date', 'extended_date_1', 'final_due_date',
            'closure_date', 'is_extended', 'extension_count',
            'a_incentive_amount', 'r_incentive_amount', 'total_incentive_amount',
            'final_incentive_amount', 'reward_amount', 'deduction_amount',
            'final_amount', 'total_reward_amount',
            'claimable', 'created_at', 'deleted_at',
            'payout_status', 'payout_remark', 'payout_updated_by', 'payout_updated_at',
            'payout_closed_by', 'payout_closed_at',
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
        $atem = Atem::withTrashed()->with([
            'arci',
            'referenceLinks',
            'attachments',
            'status',
            'progress',
            'outlets',
            'areaManagers',
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

        // Once payout has been marked Closed/Paid, the card is permanently locked —
        // no field may be changed by anyone, including SuperAdmin (superadmin_override
        // does not apply here).
        if ($atem->payout_status === 'Closed') {
            return response()->json([
                'success' => false,
                'message' => 'This ATEM cannot be edited because its payout has already been closed.',
            ], 403);
        }

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'pillar_id'          => 'nullable|integer|exists:pillars,id',
            'reward_amount'      => 'nullable|numeric',
            'deduction_amount'   => 'nullable|numeric',
            'is_deducted'        => 'nullable|boolean',
            'outlet_ids'         => 'nullable|array',
            'outlet_ids.*'       => 'integer',
            'area_manager_ids'   => 'nullable|array',
            'area_manager_ids.*' => 'integer',
            'level_structure_id' => 'nullable|integer|exists:level_structures,id',
            'incentive_rule_id'  => 'nullable|integer|exists:incentive_rules,id',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'is_extended'        => 'boolean',
            'extended_date_1'    => 'nullable|date',
            'atem_status_id'     => 'nullable|integer|exists:atem_statuses,id',
            'remarks'            => 'nullable|string',
            'updated_by'         => 'nullable|integer',
            'incentive_approved'  => 'boolean',
            'finalize'            => 'boolean',
            'superadmin_override' => 'nullable|boolean',
        ]);

        $level  = !empty($data['level_structure_id']) ? LevelStructure::find($data['level_structure_id']) : null;
        $rule   = !empty($data['incentive_rule_id']) ? IncentiveRule::find($data['incentive_rule_id']) : null;
        $status = !empty($data['atem_status_id']) ? AtemStatus::find($data['atem_status_id']) : null;
        $statusValue = $status ? $status->value : null;

        // Non-issuer SuperAdmin status-change guard. superadmin_override is only ever
        // set server-side (odb's api.php resolves $is_api_superadmin itself, dev-override
        // aware), so this cannot be spoofed by the client. Terminal-original-status cards
        // are excluded — that flow's Remarks field is locked client-side and doesn't
        // require a remark today.
        $originalStatusId    = (int) $atem->atem_status_id;
        $originalStatus      = AtemStatus::find($originalStatusId);
        $originalStatusValue = $originalStatus ? $originalStatus->value : null;
        $actorId             = (int) ($data['updated_by'] ?? 0);
        $isSuperAdminActor   = !empty($data['superadmin_override']);

        $superadminTerminalStatuses = ['Completed', 'Failed', 'Completed with Extension'];
        $isNonIssuerSuperAdminStatusEdit = $isSuperAdminActor
            && $actorId !== (int) $atem->issuer_staff_id
            && array_key_exists('atem_status_id', $data)
            && (int) ($data['atem_status_id'] ?? 0) !== $originalStatusId
            && !in_array($originalStatusValue, $superadminTerminalStatuses, true);

        $superAdminStatusChangeRemark = '';
        if ($isNonIssuerSuperAdminStatusEdit) {
            $superAdminStatusChangeRemark = trim((string) ($data['remarks'] ?? ''));
            if ($superAdminStatusChangeRemark === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'A remark is required when a SuperAdmin changes the status of an ATEM they did not issue.',
                ], 422);
            }
        }

        // Extension handling: only one extension date is permitted.
        $isExtended     = !empty($data['is_extended']);
        $ext1           = $isExtended ? ($data['extended_date_1'] ?? null) : null;
        $extensionCount = $ext1 ? 1 : 0;

        // Final due date follows the extension date when present, otherwise the end date.
        $finalDue = $data['end_date'] ?? null;
        if ($ext1) {
            $finalDue = $ext1;
        }

        // Once an extension date has been recorded, only Completed, Completed with Extension, Extended, or Failed are valid.
        // If the card is already Completed with Extension, the issuer may only revert to Extended.
        // SuperAdmin may bypass this restriction (e.g. to revert a completed extended card to Draft).
        if ($atem->is_extended && $atem->extended_date_1 && empty($data['superadmin_override'])) {
            $newStatus = AtemStatus::find($data['atem_status_id'] ?? null);
            $currentStatusValue = $atem->status ? $atem->status->value : null;
            if ($currentStatusValue === 'Completed with Extension') {
                if ($newStatus && $newStatus->value !== 'Extended') {
                    return response()->json([
                        'success' => false,
                        'message' => 'A "Completed with Extension" card can only be reverted to "Extended".',
                    ], 422);
                }
            } elseif ($newStatus && !in_array($newStatus->value, ['Completed', 'Completed with Extension', 'Extended', 'Failed'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status cannot be changed to "' . $newStatus->value . '" once an extension date has been recorded. Only Completed, Completed with Extension, Extended, or Failed are permitted.',
                ], 422);
            }
        }

        // Closure date is the date the ATEM was actually closed (terminal status),
        // not the Final Due Date. Preserve it if already set on a re-save.
        $closingStatuses = ['Completed', 'Completed with Excellence', 'Completed with Extension', 'Failed'];
        $closedBy = $atem->closed_by;
        // SuperAdmin reverting a terminal card to Draft clears closure tracking.
        if (!empty($data['superadmin_override']) && $statusValue === 'Draft') {
            $closedBy = null;
        } elseif (!in_array($statusValue, $closingStatuses, true) && !($isExtended && $ext1)) {
            // Reverting to a non-closing, non-extended status (e.g. issuer reverts Completed → Active/Draft).
            $closedBy = null;
        }
        if ($statusValue !== null && in_array($statusValue, $closingStatuses, true)) {
            $closureDate = $atem->closure_date ?: now()->toDateString();
            // Only record closed_by on the first transition into a terminal status.
            if ($closedBy === null) {
                $closedBy = $data['updated_by'] ?? null;
            }
        } elseif ($isExtended && $ext1) {
            // Extended closure date equals the extension date.
            $closureDate = $ext1;
        } else {
            $closureDate = null;
        }

        $arciMembers = $atem->arci()->get();
        $incentivisedACount = $arciMembers->where('role', 'A')->where('is_incentivised', true)->count();
        $incentivisedRCount = $arciMembers->where('role', 'R')->where('is_incentivised', true)->count();
        $incentive = $this->calculator->calculate($level, $rule, $statusValue, $incentivisedACount, $incentivisedRCount);

        // Determine the final (approved/actual) incentive payout amount.
        $approvedByIssuer = $request->boolean('incentive_approved', false);
        if ($statusValue === 'Failed') {
            $finalIncentive   = 0.0;
            $approvedByIssuer = false;
        } elseif ($isExtended) {
            // Issuer explicitly approves or denies the estimated total.
            $finalIncentive = $approvedByIssuer ? $incentive['total'] : 0.0;
        } elseif (in_array($statusValue, ['Completed', 'Completed with Excellence'], true)) {
            $finalIncentive   = $incentive['total'];
            $approvedByIssuer = true;
        } else {
            // Draft / Active — no final decision yet.
            $finalIncentive   = 0.0;
            $approvedByIssuer = false;
        }

        // Outlet-type cards: the signed final_amount is only decided once the
        // card reaches a closing status, mirroring final_incentive_amount above.
        // is_deducted picks which of the two fixed amounts (configured up
        // front) actually applies.
        $finalAmount = 0.0;
        if ((int) $atem->atem_type === 2 && in_array($statusValue, $closingStatuses, true)) {
            $finalAmount = !empty($data['is_deducted'])
                ? -1 * (float) ($data['deduction_amount'] ?? $atem->deduction_amount ?? 0)
                : (float) ($data['reward_amount'] ?? $atem->reward_amount ?? 0);
        }

        $atem->fill([
            'title'                  => $data['title'],
            'description'            => $data['description'] ?? null,
            'pillar_id'              => $data['pillar_id'] ?? null,
            'reward_amount'          => $data['reward_amount'] ?? null,
            'deduction_amount'       => $data['deduction_amount'] ?? null,
            'final_amount'           => $finalAmount,
            'level_structure_id'     => $data['level_structure_id'] ?? null,
            'incentive_rule_id'      => $data['incentive_rule_id'] ?? null,
            'base_incentive'         => $incentive['base'],
            'start_date'             => $data['start_date'] ?? null,
            'end_date'               => $data['end_date'] ?? null,
            'is_extended'            => $isExtended,
            'extended_date_1'        => $ext1,
            'extension_count'        => $extensionCount,
            'final_due_date'         => $finalDue,
            'closure_date'           => $closureDate,
            'atem_status_id'         => $data['atem_status_id'] ?? null,
            'remarks'                => $data['remarks'] ?? null,
            'a_incentive_amount'     => $incentive['a'],
            'r_incentive_amount'     => $incentive['r'],
            'total_incentive_amount' => $incentive['total'],
            'final_incentive_amount' => $finalIncentive,
            'claimable'              => $incentive['claimable'],
            'incentive_approved'     => $approvedByIssuer,
            'updated_by'             => $data['updated_by'] ?? null,
            'closed_by'              => $closedBy,
        ]);

        $atem->save();

        if ($isNonIssuerSuperAdminStatusEdit) {
            AtemAuditLogger::log(
                $atem->id,
                'status_changed_by_superadmin',
                $actorId,
                'Status changed by non-issuer SuperAdmin (staff #' . $actorId . ') from status #' . $originalStatusId
                    . ' to status #' . (int) $atem->atem_status_id . '. Remark: ' . $superAdminStatusChangeRemark
            );
        }

        if (array_key_exists('outlet_ids', $data)) {
            $atem->outlets()->delete();
            foreach (array_unique($data['outlet_ids'] ?? []) as $outletId) {
                $atem->outlets()->create(['outlet_id' => (int) $outletId]);
            }
        }

        if (array_key_exists('area_manager_ids', $data)) {
            $atem->areaManagers()->delete();
            foreach (array_unique($data['area_manager_ids'] ?? []) as $areaManagerId) {
                $atem->areaManagers()->create(['staff_id' => (int) $areaManagerId]);
            }
        }

        $this->recalcBonusEligibility($atem);

        return response()->json([
            'success' => true,
            'data'    => $atem->fresh(['arci', 'referenceLinks', 'attachments', 'status', 'outlets', 'areaManagers', 'pillar']),
        ]);
    }

    /**
     * PUT /api/atem/{id}/suspended-fields
     * While a card is Suspended, only the Issuer may still update Title and
     * Description. Everything else (level, rule, timeline, status, ARCI,
     * incentive) stays frozen until the card is unsuspended.
     */
    public function updateSuspendedFields(Request $request, int $id): JsonResponse
    {
        $atem = Atem::with('status')->findOrFail($id);

        if (!$atem->status || $atem->status->value !== 'Suspended') {
            return response()->json([
                'success' => false,
                'message' => 'This ATEM card is not suspended.',
            ], 422);
        }

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'updated_by'  => 'nullable|integer',
        ]);

        $actorId = (int) ($data['updated_by'] ?? 0);
        if ($actorId === 0 || $actorId !== (int) $atem->issuer_staff_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the Issuer can edit this ATEM while it is suspended.',
            ], 403);
        }

        $atem->title       = $data['title'];
        $atem->description = $data['description'] ?? null;
        $atem->updated_by  = $actorId;
        $atem->save();

        return response()->json([
            'success' => true,
            'data'    => $atem->fresh(['arci', 'referenceLinks', 'attachments', 'status']),
        ]);
    }

    /**
     * DELETE /api/atem/{id}
     * Soft-deletes an ATEM of any status. Only the Issuer may delete Draft/Active/Extended/Suspended
     * cards; a SuperAdmin may delete any ATEM regardless of status.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $atem = Atem::with('status')->findOrFail($id);

        $actorId = (int) $request->input('actor_id', 0);
        $isSuperAdminActor = (bool) $request->input('superadmin_override', false);

        $terminalStatuses = ['Completed', 'Completed with Excellence', 'Completed with Extension', 'Failed'];
        if ($atem->status && in_array($atem->status->value, $terminalStatuses, true) && !$isSuperAdminActor) {
            return response()->json([
                'success' => false,
                'message' => 'Completed and Failed ATEMs cannot be deleted.',
            ], 403);
        }

        if ($actorId === 0 || ($actorId !== (int) $atem->issuer_staff_id && !$isSuperAdminActor)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the Issuer or a SuperAdmin can delete this ATEM.',
            ], 403);
        }

        $remarks = trim((string) $request->input('remarks', ''));
        if ($remarks === '') {
            return response()->json([
                'success' => false,
                'message' => 'A remark is required when deleting an ATEM card.',
            ], 422);
        }

        $deletedStatusId = DB::table('atem_statuses')
            ->where('value', 'Deleted')
            ->whereNull('deleted_at')
            ->value('id');

        $atem->remarks        = $remarks;
        $atem->closed_by      = $actorId;
        if ($deletedStatusId) {
            $atem->atem_status_id = (int) $deletedStatusId;
        }
        $atem->save();

        AtemAuditLogger::log(
            $atem->id,
            'deleted',
            $actorId,
            'Card deleted by staff #' . $actorId . '. Remark: ' . $remarks
        );

        $atem->delete();

        $this->recalcBonusEligibility($atem);

        return response()->json(['success' => true]);
    }

    /**
     * Suspend an ATEM card. Allowed by grade 4/5/SuperAdmin only (enforced on frontend).
     * Sets status to Suspended and resets incentive amounts. The record is not
     * soft-deleted — Title, Description, reference links, and attachments
     * remain editable by the Issuer while suspended.
     */
    public function suspend(int $id, Request $request): JsonResponse
    {
        $atem = Atem::with('status')->findOrFail($id);

        if ($atem->status && $atem->status->value === 'Suspended') {
            return response()->json([
                'success' => false,
                'message' => 'This ATEM card has already been suspended.',
            ], 403);
        }

        if ($atem->payout_status === 'Closed') {
            return response()->json([
                'success' => false,
                'message' => 'This ATEM cannot be suspended because its payout has already been closed.',
            ], 403);
        }

        $actorId = (int) $request->input('actor_id', 0);
        if ($actorId === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Actor ID is required.',
            ], 422);
        }

        $remarks = trim((string) $request->input('remarks', ''));
        if ($remarks === '') {
            return response()->json([
                'success' => false,
                'message' => 'A remark is required when suspending an ATEM card.',
            ], 422);
        }

        $suspendedStatusId = DB::table('atem_statuses')
            ->where('value', 'Suspended')
            ->whereNull('deleted_at')
            ->value('id');

        $atem->pre_suspension_status_id = $atem->atem_status_id;
        $atem->atem_status_id           = (int) $suspendedStatusId;
        $atem->suspended_by             = $actorId;
        $atem->closed_by              = $actorId;
        $atem->suspended_remark       = $remarks;
        $atem->closure_date           = now()->toDateString();
        $atem->a_incentive_amount     = 0.0;
        $atem->r_incentive_amount     = 0.0;
        $atem->total_incentive_amount = 0.0;
        $atem->final_incentive_amount = 0.0;
        $atem->claimable              = false;
        $atem->incentive_approved     = false;
        $atem->save();

        AtemAuditLogger::log(
            $atem->id,
            'suspended',
            $actorId,
            'Card suspended by staff #' . $actorId . '. Remark: ' . $remarks
        );

        return response()->json(['success' => true]);
    }

    /**
     * Unsuspend a previously suspended ATEM card. Restores to the original pre-suspension
     * status and recalculates incentive.
     * Allowed by grade 4/5, SuperAdmin, or the card's Issuer only (enforced on frontend).
     */
    public function unsuspend(int $id, Request $request): JsonResponse
    {
        $atem = Atem::with(array('status', 'arci', 'levelStructure', 'incentiveRule'))
            ->findOrFail($id);

        if (!$atem->status || $atem->status->value !== 'Suspended') {
            return response()->json(array(
                'success' => false,
                'message' => 'This ATEM card is not suspended.',
            ), 422);
        }

        $actorId = (int) $request->input('actor_id', 0);
        if ($actorId === 0) {
            return response()->json(array(
                'success' => false,
                'message' => 'Actor ID is required.',
            ), 422);
        }

        $restoreStatusId = $atem->pre_suspension_status_id;
        if (!$restoreStatusId) {
            $restoreStatusId = DB::table('atem_statuses')
                ->where('value', 'Active')
                ->whereNull('deleted_at')
                ->value('id');
        }

        $restoreStatus      = AtemStatus::find((int) $restoreStatusId);
        $restoreStatusValue = $restoreStatus ? $restoreStatus->value : 'Active';

        $level   = $atem->levelStructure;
        $rule    = $atem->incentiveRule;
        $arci    = $atem->arci;
        $incentivisedACount = $arci->where('role', 'A')->where('is_incentivised', true)->count();
        $incentivisedRCount = $arci->where('role', 'R')->where('is_incentivised', true)->count();
        $incentive = $this->calculator->calculate($level, $rule, $restoreStatusValue, $incentivisedACount, $incentivisedRCount);

        $finalIncentive    = 0.0;
        $incentiveApproved = false;
        if (in_array($restoreStatusValue, array('Completed', 'Completed with Excellence'), true)) {
            $finalIncentive    = $incentive['total'];
            $incentiveApproved = true;
        } elseif ($restoreStatusValue === 'Extended' && $atem->incentive_approved) {
            $finalIncentive    = $incentive['total'];
            $incentiveApproved = true;
        }

        $atem->atem_status_id         = (int) $restoreStatusId;
        $atem->closed_by              = null;
        $atem->a_incentive_amount     = $incentive['a'];
        $atem->r_incentive_amount     = $incentive['r'];
        $atem->total_incentive_amount = $incentive['total'];
        $atem->final_incentive_amount = $finalIncentive;
        $atem->claimable              = $incentive['claimable'];
        $atem->incentive_approved     = $incentiveApproved;
        $atem->updated_by             = $actorId;
        $atem->save();

        AtemAuditLogger::log(
            $atem->id,
            'unsuspended',
            $actorId,
            'Card unsuspended by staff #' . $actorId . '. Restored to status: ' . $restoreStatusValue
        );

        return response()->json(array('success' => true));
    }

    /**
     * PATCH /api/atem/{id}/payout-status
     * Sets or updates the payout status for an ATEM once it has reached a terminal
     * status. Allowed by grade 4/5, SuperAdmin, or People Management (dept 17) staff
     * (enforced on the odb frontend). Once set to 'Closed' it can never change again.
     */
    public function updatePayoutStatus(int $id, Request $request): JsonResponse
    {
        $atem = Atem::with('status')->findOrFail($id);

        $isSuperAdmin = (bool) $request->input('is_superadmin', false);
        if ($atem->payout_status === 'Closed' && !$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Payout status has already been closed and cannot be changed.',
            ], 403);
        }

        $terminalStatuses = ['Completed', 'Completed with Excellence', 'Completed with Extension', 'Failed'];
        if (!$atem->status || !in_array($atem->status->value, $terminalStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Payout status can only be set once the ATEM has reached a terminal status.',
            ], 403);
        }

        $actorId = (int) $request->input('actor_id', 0);
        if ($actorId === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Actor ID is required.',
            ], 422);
        }

        $allowedPayoutStatuses = ['Payout In Progress', 'No Payout', 'Closed'];
        $payoutStatus = (string) $request->input('payout_status', '');
        if (!in_array($payoutStatus, $allowedPayoutStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payout status.',
            ], 422);
        }

        $remarks = trim((string) $request->input('remarks', ''));
        if ($remarks === '') {
            return response()->json([
                'success' => false,
                'message' => 'A remark is required when changing payout status.',
            ], 422);
        }

        $atem->payout_status     = $payoutStatus;
        $atem->payout_remark     = $remarks;
        $atem->payout_updated_by = $actorId;
        $atem->payout_updated_at = now();

        if ($payoutStatus === 'Closed') {
            $atem->payout_closed_by = $actorId;
            $atem->payout_closed_at = now();
        } elseif ($isSuperAdmin) {
            // Reopening a previously-closed record via SuperAdmin override.
            $atem->payout_closed_by = null;
            $atem->payout_closed_at = null;
        }

        $atem->save();

        AtemAuditLogger::log(
            $atem->id,
            'payout_status_changed',
            $actorId,
            'Payout status changed to ' . $payoutStatus . ' by staff #' . $actorId . '. Remark: ' . $remarks
        );

        return response()->json([
            'success' => true,
            'data'    => $atem->fresh(['status']),
        ]);
    }

    /**
     * PATCH /api/atem/payout-status/bulk-lock
     * Closes payout for every eligible id (terminal status, not already Closed).
     * Ineligible ids are skipped rather than failing the whole batch.
     */
    public function bulkLockPayout(Request $request): JsonResponse
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $remarks = trim((string) $request->input('remarks', ''));
        $actorId = (int) $request->input('actor_id', 0);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No ATEM ids supplied.'], 422);
        }
        if ($actorId === 0) {
            return response()->json(['success' => false, 'message' => 'Actor ID is required.'], 422);
        }
        if ($remarks === '') {
            return response()->json(['success' => false, 'message' => 'A remark is required when locking payout.'], 422);
        }

        $terminalStatuses = ['Completed', 'Completed with Excellence', 'Completed with Extension', 'Failed'];
        $locked = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, $remarks, $actorId, $terminalStatuses, &$locked, &$skipped) {
            $atems = Atem::with('status')->whereIn('id', $ids)->get();
            foreach ($atems as $atem) {
                if ($atem->payout_status === 'Closed' || !$atem->status || !in_array($atem->status->value, $terminalStatuses, true)) {
                    $skipped++;
                    continue;
                }

                $atem->payout_status     = 'Closed';
                $atem->payout_remark     = $remarks;
                $atem->payout_updated_by = $actorId;
                $atem->payout_updated_at = now();
                $atem->payout_closed_by  = $actorId;
                $atem->payout_closed_at  = now();
                $atem->save();

                AtemAuditLogger::log(
                    $atem->id,
                    'payout_bulk_closed',
                    $actorId,
                    'Payout closed in bulk by staff #' . $actorId . '. Remark: ' . $remarks
                );

                $locked++;
            }
        });

        return response()->json(['success' => true, 'locked' => $locked, 'skipped' => $skipped]);
    }

    /**
     * PATCH /api/atem/payout-status/bulk-unlock
     * Reopens payout for every currently-Closed id in the batch. Allowed for
     * grade 2+, People Management (dept 17), or SuperAdmin — authorization is
     * fully resolved by the odb frontend (api.php) before this endpoint is
     * ever called, the same trust boundary bulkLockPayout() already relies on.
     */
    public function bulkUnlockPayout(Request $request): JsonResponse
    {
        $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
        $remarks = trim((string) $request->input('remarks', ''));
        $actorId = (int) $request->input('actor_id', 0);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No ATEM ids supplied.'], 422);
        }
        if ($actorId === 0) {
            return response()->json(['success' => false, 'message' => 'Actor ID is required.'], 422);
        }
        if ($remarks === '') {
            return response()->json(['success' => false, 'message' => 'A remark is required when unlocking payout.'], 422);
        }

        $unlocked = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, $remarks, $actorId, &$unlocked, &$skipped) {
            $atems = Atem::whereIn('id', $ids)->get();
            foreach ($atems as $atem) {
                if ($atem->payout_status !== 'Closed') {
                    $skipped++;
                    continue;
                }

                $atem->payout_status     = null;
                $atem->payout_remark     = $remarks;
                $atem->payout_updated_by = $actorId;
                $atem->payout_updated_at = now();
                $atem->payout_closed_by  = null;
                $atem->payout_closed_at  = null;
                $atem->save();

                AtemAuditLogger::log(
                    $atem->id,
                    'payout_bulk_unlocked',
                    $actorId,
                    'Payout unlocked in bulk by staff #' . $actorId . '. Remark: ' . $remarks
                );

                $unlocked++;
            }
        });

        return response()->json(['success' => true, 'unlocked' => $unlocked, 'skipped' => $skipped]);
    }
}
