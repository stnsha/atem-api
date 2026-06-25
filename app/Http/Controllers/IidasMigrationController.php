<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IidasMigrationController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        if (!Schema::hasTable('iidas_migration_preview')) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'meta'    => [
                    'total'        => 0,
                    'per_page'     => 50,
                    'current_page' => 1,
                    'last_page'    => 1,
                ],
            ]);
        }

        $perPage   = min(100, max(1, (int) $request->query('per_page', 50)));
        $status    = $request->query('status');
        $committed = $request->query('committed');

        $query = DB::table('iidas_migration_preview')->orderBy('source_id');

        if ($status) {
            $query->where('mapped_status', $status);
        }

        if ($committed === '0') {
            $query->whereNull('committed_at');
        } elseif ($committed === '1') {
            $query->whereNotNull('committed_at');
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function summary(): JsonResponse
    {
        if (!Schema::hasTable('iidas_migration_preview')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = DB::table('iidas_migration_preview')
            ->selectRaw('
                mapped_status,
                COUNT(*) as total,
                SUM(CASE WHEN committed_at IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN committed_at IS NOT NULL THEN 1 ELSE 0 END) as committed
            ')
            ->groupBy('mapped_status')
            ->orderBy('mapped_status')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }
}
