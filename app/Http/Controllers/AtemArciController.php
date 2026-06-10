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

    /**
     * POST /api/atem/{id}/arci
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'staff_id'      => 'required|integer',
            'staff_dept_id' => 'nullable|integer',
            'role'          => 'required|in:A,R,C,I',
            'assigned_by'   => 'nullable|integer',
        ]);

        if ($data['role'] === 'A') {
            $hasA = AtemArci::where('atem_id', $atem->id)->where('role', 'A')->exists();
            if ($hasA) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role A is already assigned. Remove the existing A before assigning a new one.',
                ], 422);
            }
        }

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
                'role'          => $data['role'],
                'staff_dept_id' => $data['staff_dept_id'] ?? null,
                'assigned_by'   => $data['assigned_by'] ?? null,
            ]);
        } else {
            AtemArci::create([
                'atem_id'       => $atem->id,
                'staff_id'      => $data['staff_id'],
                'staff_dept_id' => $data['staff_dept_id'] ?? null,
                'role'          => $data['role'],
                'assigned_by'   => $data['assigned_by'] ?? null,
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
