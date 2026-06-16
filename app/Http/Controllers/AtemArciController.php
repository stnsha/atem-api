<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemArci;
use App\Services\AtemAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemArciController extends Controller
{
    private const ROLES = ['A', 'R', 'C', 'I'];

    private function getRuleLimits(?string $ruleCode): array
    {
        $map = [
            'rule 1' => ['maxA' => 2, 'maxR' => 0],
            'rule 2' => ['maxA' => 1, 'maxR' => 0],
            'rule 3' => ['maxA' => 1, 'maxR' => 2],
            'rule 4' => ['maxA' => 2, 'maxR' => 2],
            'rule 5' => ['maxA' => 1, 'maxR' => 1],
            'rule 6' => ['maxA' => 2, 'maxR' => 1],
        ];
        $key = strtolower(trim((string) $ruleCode));
        return $map[$key] ?? ['maxA' => 2, 'maxR' => 2];
    }

    /**
     * POST /api/atem/{id}/arci
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'staff_id'        => 'required|integer',
            'staff_dept_id'   => 'nullable|integer',
            'role'            => 'required|in:A,R,C,I',
            'is_incentivised' => 'nullable|boolean',
            'assigned_by'     => 'nullable|integer',
        ]);

        $existing = AtemArci::withTrashed()
            ->where('atem_id', $atem->id)
            ->where('staff_id', $data['staff_id'])
            ->first();

        if ($existing) {
            if (!$existing->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This staff member is already assigned on this ATEM.',
                ], 422);
            }
            // Restore the soft-deleted record and update the role instead of inserting.
            $existing->restore();
            $existing->update([
                'role'            => $data['role'],
                'staff_dept_id'   => $data['staff_dept_id'] ?? null,
                'is_incentivised' => $data['is_incentivised'] ?? false,
                'assigned_by'     => $data['assigned_by'] ?? null,
            ]);
        } else {
            AtemArci::create([
                'atem_id'         => $atem->id,
                'staff_id'        => $data['staff_id'],
                'staff_dept_id'   => $data['staff_dept_id'] ?? null,
                'role'            => $data['role'],
                'is_incentivised' => $data['is_incentivised'] ?? false,
                'assigned_by'     => $data['assigned_by'] ?? null,
            ]);
        }

        AtemAuditLogger::log(
            $atem->id,
            'arci_added',
            $data['assigned_by'] ?? null,
            'Added ' . $data['role'] . ' member (staff #' . $data['staff_id'] . ').'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/arci
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'staff_id' => 'required|integer',
            'role'     => 'nullable|in:A,R,C,I',
            'actor_id' => 'nullable|integer',
        ]);

        $query = AtemArci::where('atem_id', $atem->id)->where('staff_id', $data['staff_id']);
        if (!empty($data['role'])) {
            $query->where('role', $data['role']);
        }
        $query->delete();

        $roleLabel = $data['role'] ?? 'unknown';
        AtemAuditLogger::log(
            $atem->id,
            'arci_removed',
            $data['actor_id'] ?? null,
            'Removed ' . $roleLabel . ' member (staff #' . $data['staff_id'] . ').'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/arci/role/{role}
     */
    public function destroyByRole(Request $request, int $id, string $role): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        if (!in_array($role, self::ROLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role.',
            ], 422);
        }

        AtemArci::where('atem_id', $atem->id)->where('role', $role)->delete();

        $actorId = $request->input('actor_id');
        AtemAuditLogger::log(
            $atem->id,
            'arci_role_cleared',
            $actorId ? (int) $actorId : null,
            'Cleared all ' . $role . ' members.'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    /**
     * PATCH /api/atem/{id}/arci/{arci_id}
     * Updates the is_incentivised flag on a single ARCI member.
     */
    public function update(Request $request, int $id, int $arci_id): JsonResponse
    {
        $atem   = Atem::findOrFail($id);
        $member = AtemArci::where('atem_id', $atem->id)->where('id', $arci_id)->firstOrFail();

        $data = $request->validate([
            'is_incentivised' => 'required|boolean',
        ]);

        if ($data['is_incentivised']) {
            $ruleCode = $atem->incentiveRule ? $atem->incentiveRule->code : null;
            $limits   = $this->getRuleLimits($ruleCode);
            $maxForRole = $member->role === 'A' ? $limits['maxA'] : $limits['maxR'];
            $currentCount = AtemArci::where('atem_id', $atem->id)
                ->where('role', $member->role)
                ->where('id', '!=', $arci_id)
                ->where('is_incentivised', true)
                ->count();
            if ($currentCount >= $maxForRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Max incentivised ' . $member->role . ' members (' . $maxForRole . ') already reached for this rule.',
                ], 422);
            }
        }

        $member->update(['is_incentivised' => $data['is_incentivised']]);

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    private function grouped(int $atemId): array
    {
        $members = AtemArci::where('atem_id', $atemId)->orderBy('id')->get();

        $grouped = ['A' => [], 'R' => [], 'C' => [], 'I' => []];
        foreach ($members as $member) {
            $grouped[$member->role][] = $member;
        }

        return $grouped;
    }
}
