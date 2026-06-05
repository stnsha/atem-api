<?php

namespace App\Http\Controllers;

use App\Models\Atem;
use App\Models\AtemProgress;
use App\Services\AtemAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemProgressController extends Controller
{
    /**
     * GET /api/atem/{id}/progress
     */
    public function index(int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->progress($atem->id),
        ]);
    }

    /**
     * POST /api/atem/{id}/progress
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'status'     => 'required|in:red,yellow,green',
            'remark'     => 'nullable|string|max:2000',
            'created_by' => 'nullable|integer',
        ]);

        AtemProgress::create([
            'atem_id'    => $atem->id,
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'status'     => $data['status'],
            'remark'     => $data['remark'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        AtemAuditLogger::log(
            $atem->id,
            'progress_added',
            $data['created_by'] ?? null,
            'Added progress update: ' . $data['start_date'] . ' to ' . $data['end_date'] . ' [' . $data['status'] . '].'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->progress($atem->id),
        ]);
    }

    /**
     * PUT /api/atem/{id}/progress/{progressId}
     */
    public function update(Request $request, int $id, int $progressId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'status'     => 'required|in:red,yellow,green',
            'remark'     => 'nullable|string|max:2000',
            'actor_id'   => 'nullable|integer',
        ]);

        AtemProgress::where('atem_id', $atem->id)->where('id', $progressId)->update([
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'status'     => $data['status'],
            'remark'     => $data['remark'] ?? null,
        ]);

        $actorId = isset($data['actor_id']) ? (int) $data['actor_id'] : null;
        AtemAuditLogger::log(
            $atem->id,
            'progress_updated',
            $actorId,
            'Updated progress entry #' . $progressId . ': ' . $data['start_date'] . ' to ' . $data['end_date'] . ' [' . $data['status'] . '].'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->progress($atem->id),
        ]);
    }

    /**
     * DELETE /api/atem/{id}/progress/{progressId}
     */
    public function destroy(Request $request, int $id, int $progressId): JsonResponse
    {
        $atem = Atem::findOrFail($id);

        AtemProgress::where('atem_id', $atem->id)->where('id', $progressId)->delete();

        $actorId = $request->input('actor_id');
        AtemAuditLogger::log(
            $atem->id,
            'progress_removed',
            $actorId ? (int) $actorId : null,
            'Removed progress entry #' . $progressId . '.'
        );

        return response()->json([
            'success' => true,
            'data'    => $this->progress($atem->id),
        ]);
    }

    private function progress(int $atemId)
    {
        return AtemProgress::where('atem_id', $atemId)->orderBy('start_date')->get();
    }
}
