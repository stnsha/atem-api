<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemArci;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemArciController extends Controller
{
    private const ROLES = ['A', 'R', 'C', 'I'];

    /**
     * POST /api/atem/{id}/arci
     * Adds a single ARCI member. Role A is unique per card; a staff member can
     * hold only one role per card.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'staff_id'      => 'required|integer',
            'department_id' => 'nullable|integer',
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

        $exists = AtemArci::where('atem_id', $atem->id)
            ->where('staff_id', $data['staff_id'])
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This staff member is already assigned on this ATEM.',
            ], 422);
        }

        AtemArci::create([
            'atem_id'       => $atem->id,
            'staff_id'      => $data['staff_id'],
            'department_id' => $data['department_id'] ?? null,
            'role'          => $data['role'],
            'assigned_by'   => $data['assigned_by'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/arci
     * Removes one member by staff id (and optionally role).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'staff_id' => 'required|integer',
            'role'     => 'nullable|in:A,R,C,I',
        ]);

        $query = AtemArci::where('atem_id', $atem->id)->where('staff_id', $data['staff_id']);
        if (!empty($data['role'])) {
            $query->where('role', $data['role']);
        }
        $query->delete();

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/arci/role/{role}
     * Removes every member assigned to a role.
     */
    public function destroyByRole(int $id, string $role): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        if (!in_array($role, self::ROLES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role.',
            ], 422);
        }

        AtemArci::where('atem_id', $atem->id)->where('role', $role)->delete();

        return response()->json([
            'success' => true,
            'data'    => $this->grouped($atem->id),
        ]);
    }

    /**
     * Returns members for a card grouped by role, ready for the UI to render.
     *
     * @return array<string, array>
     */
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
