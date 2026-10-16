<?php

namespace App\Http\Controllers;

use App\Models\AtemExportLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtemExportLogController extends Controller
{
    /**
     * GET /api/atem/export-logs?target_staff_id={id}&limit={n}
     * Most recent export events for one staff (newest first), shown in the
     * Logs section of odb's staff_performance/edit.php.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_staff_id' => 'required|integer|min:1',
            'limit'           => 'nullable|integer|min:1|max:100',
        ]);

        $logs = AtemExportLog::where('target_staff_id', (int) $validated['target_staff_id'])
            ->orderByDesc('exported_at')
            ->orderByDesc('id')
            ->limit((int) ($validated['limit'] ?? 15))
            ->get(['id', 'target_staff_id', 'actor_staff_id', 'export_type', 'exported_at']);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * POST /api/atem/export-logs
     * Body: { target_staff_ids: int[], actor_id: int, export_type: string }
     * Records one export event per target staff in a single insert, so a bulk
     * export of many staff costs one round trip. Authorization is resolved by
     * the odb frontend (export.php) before this is called, same trust boundary
     * as bulkLockPayout().
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_staff_ids'   => 'required|array|min:1',
            'target_staff_ids.*' => 'integer|min:1',
            'actor_id'           => 'required|integer|min:1',
            'export_type'        => 'required|string|max:32',
        ]);

        $now = now();
        $rows = array_map(fn (int $targetId) => [
            'target_staff_id' => $targetId,
            'actor_staff_id'  => (int) $validated['actor_id'],
            'export_type'     => $validated['export_type'],
            'exported_at'     => $now,
        ], array_values(array_unique(array_map('intval', $validated['target_staff_ids']))));

        foreach (array_chunk($rows, 500) as $chunk) {
            AtemExportLog::insert($chunk);
        }

        return response()->json([
            'success' => true,
            'logged'  => count($rows),
        ]);
    }
}
