<?php

namespace App\Console\Commands;

use App\Services\IidasMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class MigrateIidasAtems extends Command
{
    protected $signature = 'iidas:migrate-atems
                            {--per-page=50 : ATEMs fetched per batch}
                            {--dry-run     : Preview counts without writing to the database}';

    protected $description = 'Migrate ATEM data from IIDAS (ODB) into atem_local via the ODB API';

    // IIDAS task.status → atem_status_id
    // 0 = pending → 1 (Draft), 1 = in progress → 2 (Active), 2 = complete → 3 (Completed)
    private array $statusMap = [0 => 1, 1 => 2, 2 => 3];

    private string $iidasBaseUrl = 'http://octopusdb.info:8080/odb/iidas';

    public function handle(IidasMigrationService $iidas): int
    {
        $perPage = max(1, (int) $this->option('per-page'));
        $dryRun  = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[dry-run] No changes will be written to the database.');
        }

        $counters = [
            'atems'        => 0,
            'soft_deleted' => 0,
            'arci'         => 0,
            'progress'     => 0,
            'refs'         => 0,
            'attachments'  => 0,
            'skipped'      => 0,
            'warnings'     => 0,
        ];

        $page       = 1;
        $totalPages = 1;

        while ($page <= $totalPages) {
            $result     = $iidas->getAtems($page, $perPage);
            $totalPages = (int) ($result['pages'] ?? 1);
            $atems      = $result['data'] ?? [];

            if (empty($atems)) {
                break;
            }

            $ids       = array_column($atems, 'id');
            $relations = $iidas->getAtemRelations($ids);

            $picsMap = $this->groupBy($relations['pics'],        'atem_id');
            $subMap  = $this->groupBy($relations['subtasks'],    'atem_id');
            $refMap  = $this->groupBy($relations['refs'],        'atem_id');
            $attMap  = $this->groupBy($relations['attachments'], 'atem_id');

            foreach ($atems as $atem) {
                try {
                    $this->processAtem($atem, $picsMap, $subMap, $refMap, $attMap, $dryRun, $counters);
                } catch (\Throwable $e) {
                    $counters['skipped']++;
                    $this->warn("ATEM #{$atem['id']} skipped: " . $e->getMessage());
                }
            }

            $this->line("Page {$page}/{$totalPages} done — total ATEMs so far: {$counters['atems']}");
            $page++;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['ATEMs migrated',   $counters['atems']],
                ['Soft-deleted',     $counters['soft_deleted']],
                ['ARCI rows',        $counters['arci']],
                ['Progress entries', $counters['progress']],
                ['Reference links',  $counters['refs']],
                ['Attachments',      $counters['attachments']],
                ['Skipped (errors)', $counters['skipped']],
                ['Warnings',         $counters['warnings']],
            ]
        );

        return Command::SUCCESS;
    }

    private function processAtem(
        array $atem,
        array $picsMap,
        array $subMap,
        array $refMap,
        array $attMap,
        bool  $dryRun,
        array &$counters
    ): void {
        $atemId    = (int) $atem['id'];
        $createdBy = (int) $atem['created_by'];
        $statusId  = $this->statusMap[(int) $atem['status']] ?? 1;
        $now       = Carbon::now()->toDateTimeString();
        $deletedAt = ((int) $atem['recycle'] === 1) ? $now : null;

        $fullText   = $atem['action_details'] ?? '';
        $firstLine  = trim(strtok($fullText, "\n\r"));
        $title      = mb_substr($firstLine !== '' ? $firstLine : $fullText, 0, 255);
        $startDate  = $atem['start_date'] ?: null;
        $endDate    = $atem['end_date']   ?: null;
        $deptId     = !empty($atem['department_id']) ? (int) $atem['department_id'] : null;

        $newAtemId = 0;

        if (!$dryRun) {
            $newAtemId = DB::table('atems')->insertGetId([
                'title'              => $title,
                'description'        => $fullText,
                'issuer_staff_id'    => $createdBy,
                'staff_dept_id'      => $deptId,
                'level_structure_id' => 1,
                'incentive_rule_id'  => null,
                'base_incentive'     => 0,
                'start_date'         => $startDate,
                'end_date'           => $endDate,
                'final_due_date'     => $endDate,
                'atem_status_id'     => $statusId,
                'created_by'         => $createdBy,
                'created_at'         => $atem['created_at'],
                'updated_at'         => $now,
                'deleted_at'         => $deletedAt,
            ]);
        }

        $counters['atems']++;
        if ($deletedAt !== null) {
            $counters['soft_deleted']++;
        }

        // ARCI 'A' — creator
        if (!$dryRun && $newAtemId) {
            DB::table('atem_arci')->insert([
                'atem_id'      => $newAtemId,
                'staff_id'     => $createdBy,
                'staff_dept_id'=> $deptId,
                'role'         => 'A',
                'assigned_by'  => $createdBy,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
        $counters['arci']++;

        // ARCI 'R' — PICs (skip if same as creator to avoid unique constraint conflict)
        $insertedStaff = [$createdBy];
        foreach ($picsMap[$atemId] ?? [] as $pic) {
            $picId = (int) $pic['staff_id'];
            if (in_array($picId, $insertedStaff, true)) {
                continue;
            }
            $insertedStaff[] = $picId;
            if (!$dryRun && $newAtemId) {
                DB::table('atem_arci')->insert([
                    'atem_id'      => $newAtemId,
                    'staff_id'     => $picId,
                    'staff_dept_id'=> isset($pic['dept_id']) ? $pic['dept_id'] : null,
                    'role'         => 'R',
                    'assigned_by'  => $createdBy,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
            $counters['arci']++;
        }

        // atem_progress — one entry per non-deleted subtask
        foreach ($subMap[$atemId] ?? [] as $sub) {
            // Use subtask's own dates when available; fall back to ATEM dates; last resort today
            $subStart = $sub['start_date'] ?: ($startDate ?: Carbon::today()->toDateString());
            $subEnd   = $sub['end_date']   ?: ($endDate   ?: Carbon::today()->toDateString());

            if (!$dryRun && $newAtemId) {
                DB::table('atem_progress')->insert([
                    'atem_id'    => $newAtemId,
                    'start_date' => $subStart,
                    'end_date'   => $subEnd,
                    'status'     => 'green',
                    'remark'     => $sub['atem_detail'] ?: null,
                    'created_by' => (int) ($sub['created_by'] ?? $createdBy),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $counters['progress']++;
        }

        // atem_reference_links — from iidas_ref
        foreach ($refMap[$atemId] ?? [] as $ref) {
            if (!$dryRun && $newAtemId) {
                DB::table('atem_reference_links')->insert([
                    'atem_id'    => $newAtemId,
                    'name'       => $ref['name'],
                    'url'        => $ref['url'],
                    'added_by'   => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $counters['refs']++;
        }

        // atem_reference_links — IIDAS back-link (only when project is accessible)
        $projectId = (int) $atem['project_id'];
        if ((int) $atem['allow_view_project'] === 1 && $projectId > 0) {
            $backUrl = $this->iidasBaseUrl
                . '/project_detail.php?id=' . $projectId
                . '&atem_view=1&atem_id=' . $atemId;

            if (!$dryRun && $newAtemId) {
                DB::table('atem_reference_links')->insert([
                    'atem_id'    => $newAtemId,
                    'name'       => 'IIDAS',
                    'url'        => $backUrl,
                    'added_by'   => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $counters['refs']++;
        }

        // atem_attachments — file content arrives as base64 from the ODB API
        foreach ($attMap[$atemId] ?? [] as $att) {
            if (empty($att['content'])) {
                $counters['warnings']++;
                $this->warn("ATEM #{$atemId}: attachment '{$att['name']}' skipped — file missing on ODB server.");
                continue;
            }
            if (!$dryRun && $newAtemId) {
                DB::table('atem_attachments')->insert([
                    'atem_id'     => $newAtemId,
                    'name'        => $att['name'],
                    'type'        => $att['type'],
                    'size'        => (int) $att['size'],
                    'content'     => $att['content'],
                    'uploaded_by' => $createdBy,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
            $counters['attachments']++;
        }
    }

    private function groupBy(array $items, string $key): array
    {
        $map = [];
        foreach ($items as $item) {
            $k = $item[$key] ?? null;
            if ($k !== null) {
                $map[$k][] = $item;
            }
        }
        return $map;
    }
}
