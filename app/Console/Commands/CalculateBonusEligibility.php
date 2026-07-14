<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemBonusEligibility;
use App\Models\AtemStatus;
use App\Services\StaffApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class CalculateBonusEligibility extends Command
{
    protected $signature = 'atem:calculate-bonus
                            {--month= : Month number (1-12), defaults to current month. Omitted together with --year runs every month of that year}
                            {--year=  : Year, defaults to current year}
                            {--all-months : Force processing every month (1-12) of --year (or the current year) in one run}';

    protected $description = 'Calculate and upsert AtemBonusEligibility records for the given month/year, or every month of a year';

    public function handle(StaffApiService $staffApi): int
    {
        $year = (int) ($this->option('year') ?: Carbon::now()->year);

        // "--year=2026" on its own (no --month) is treated as "the whole year",
        // matching how people naturally reach for this command. Pass an explicit
        // --month to run a single month, or --all-months to force whole-year mode
        // even when the current month would otherwise be assumed.
        $runAllMonths = $this->option('all-months') || (!$this->option('month') && $this->option('year'));

        if ($runAllMonths) {
            $this->info("Calculating bonus eligibility for all months of {$year}...");
            for ($month = 1; $month <= 12; $month++) {
                $result = $this->calculateForMonth($staffApi, $month, $year);
                if ($result !== 0) {
                    return $result;
                }
            }
            $this->info("Finished calculating bonus eligibility for all months of {$year}.");
            return 0;
        }

        $month = (int) ($this->option('month') ?: Carbon::now()->month);
        return $this->calculateForMonth($staffApi, $month, $year);
    }

    private function calculateForMonth(StaffApiService $staffApi, int $month, int $year): int
    {
        $this->info("Calculating bonus eligibility for {$month}/{$year}...");
        $this->writeProgress(0, 5, "Starting {$month}/{$year}...");

        $closedStatusIds = AtemStatus::whereIn('value', array('Completed', 'Completed with Excellence', 'Completed with Extension'))
            ->pluck('id');
        $activeStatusId  = AtemStatus::where('value', 'Active')->value('id');
        $extendStatusId  = AtemStatus::where('value', 'Extended')->value('id');
        $failedStatusId  = AtemStatus::where('value', 'Failed')->value('id');

        $aggregates = array();

        // Completed ATEM: closure_date in month/year
        $this->writeProgress(1, 5, 'Processing completed ATEMs');
        $completedAtems = Atem::with(array('arci', 'status'))
            ->whereIn('atem_status_id', $closedStatusIds)
            ->whereNotNull('closure_date')
            ->whereMonth('closure_date', $month)
            ->whereYear('closure_date', $year)
            ->get();

        foreach ($completedAtems as $atem) {
            // final_incentive_amount is the actual approved payout (0 unless approved) —
            // a_incentive_amount/r_incentive_amount are always the raw rule-based estimate
            // and stay non-zero even when the issuer rejected an extended card's payout,
            // so only award incentive when the final amount was actually approved.
            $isApproved = (float) $atem->final_incentive_amount > 0;

            $incACount = $atem->arci->where('role', 'A')->where('is_incentivised', true)->count();
            $incRCount = $atem->arci->where('role', 'R')->where('is_incentivised', true)->count();

            $involved = array();

            if ($atem->issuer_staff_id) {
                $involved[$atem->issuer_staff_id] = array(
                    'dept_id'   => $atem->staff_dept_id,
                    'incentive' => 0.0,
                );
            }

            foreach ($atem->arci as $member) {
                $incentive = 0.0;
                if ($isApproved) {
                    if ($member->role === 'A' && $member->is_incentivised && $incACount > 0) {
                        $incentive = (float) $atem->a_incentive_amount / $incACount;
                    } elseif ($member->role === 'R' && $member->is_incentivised && $incRCount > 0) {
                        $incentive = (float) $atem->r_incentive_amount / $incRCount;
                    }
                }

                $involved[$member->staff_id] = array(
                    'dept_id'   => $member->staff_dept_id,
                    'incentive' => $incentive,
                );
            }

            foreach ($involved as $staffId => $data) {
                $this->ensureAggregate($aggregates, $staffId, $data['dept_id']);
                $aggregates[$staffId]['complete_count']  += 1;
                $aggregates[$staffId]['total_incentive'] += $data['incentive'];
                if ($data['dept_id']) {
                    $aggregates[$staffId]['dept_id'] = $data['dept_id'];
                }
            }
        }

        // Active ATEM: start_date in month/year
        $this->writeProgress(2, 5, 'Processing active ATEMs');
        if ($activeStatusId) {
            $activeAtems = Atem::with(array('arci'))
                ->where('atem_status_id', $activeStatusId)
                ->whereMonth('start_date', $month)
                ->whereYear('start_date', $year)
                ->get();

            $this->applyStatusCount($aggregates, $activeAtems, 'active_count');
        }

        // Extended ATEM: closure_date in month/year
        $this->writeProgress(3, 5, 'Processing extended ATEMs');
        if ($extendStatusId) {
            $extendAtems = Atem::with(array('arci'))
                ->where('atem_status_id', $extendStatusId)
                ->whereNotNull('closure_date')
                ->whereMonth('closure_date', $month)
                ->whereYear('closure_date', $year)
                ->get();

            $this->applyStatusCount($aggregates, $extendAtems, 'extend_count');
        }

        // Failed ATEM: closure_date in month/year
        $this->writeProgress(4, 5, 'Processing failed ATEMs');
        if ($failedStatusId) {
            $failedAtems = Atem::with(array('arci'))
                ->where('atem_status_id', $failedStatusId)
                ->whereNotNull('closure_date')
                ->whereMonth('closure_date', $month)
                ->whereYear('closure_date', $year)
                ->get();

            $this->applyStatusCount($aggregates, $failedAtems, 'failed_count');
        }

        // Reconcile with previously-stored rows: a staff member who had a record for this
        // month/year before (e.g. an ATEM that was Active then, or has since been deleted)
        // but no longer matches any bucket must have their counts reset to zero here,
        // rather than being left with stale non-zero values from the last calculation.
        $existingRows = AtemBonusEligibility::where('month', $month)->where('year', $year)->get();
        foreach ($existingRows as $existingRow) {
            $this->ensureAggregate($aggregates, (int) $existingRow->staff_id, $existingRow->staff_dept_id);
        }

        if (empty($aggregates)) {
            $this->info('No ATEM found for this period.');
            $this->applyEndOfMonthRemarks($month, $year);
            return 0;
        }

        // Set total_atem as sum of all status counts
        foreach ($aggregates as $staffId => &$data) {
            $data['total_atem'] = $data['complete_count']
                + $data['active_count']
                + $data['extend_count']
                + $data['failed_count'];
        }
        unset($data);

        $this->writeProgress(5, 5, 'Saving records');

        // Fetch grade and struct IDs via ODB API
        $staffIds = array_keys($aggregates);
        $odbStaff = $staffApi->getStaffInfo($staffIds);

        // Upsert each staff record (do not overwrite remark)
        foreach ($aggregates as $staffId => $data) {
            $staffInfo = isset($odbStaff[$staffId]) ? $odbStaff[$staffId] : null;

            $existing = AtemBonusEligibility::where('staff_id', $staffId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $values = array(
                'staff_dept_id'   => $data['dept_id'],
                'staff_grade'     => $staffInfo ? $staffInfo['grade']  : null,
                'staff_struct'    => $staffInfo ? $staffInfo['struct'] : null,
                'total_atem'      => $data['total_atem'],
                'complete_count'  => $data['complete_count'],
                'active_count'    => $data['active_count'],
                'extend_count'    => $data['extend_count'],
                'failed_count'    => $data['failed_count'],
                'total_incentive' => round($data['total_incentive'], 2),
            );

            if ($existing) {
                $existing->update($values);
            } else {
                AtemBonusEligibility::create(array_merge($values, array(
                    'staff_id' => $staffId,
                    'month'    => $month,
                    'year'     => $year,
                )));
            }
        }

        $this->info('Upserted ' . count($aggregates) . ' eligibility record(s).');

        $this->applyEndOfMonthRemarks($month, $year);

        return 0;
    }

    private function writeProgress(int $current, int $total, string $stage): void
    {
        $path = storage_path('app/bonus_calc_progress.json');
        file_put_contents($path, json_encode(array(
            'current' => $current,
            'total'   => $total,
            'stage'   => $stage,
        )));
    }

    private function ensureAggregate(array &$aggregates, int $staffId, $deptId): void
    {
        if (!isset($aggregates[$staffId])) {
            $aggregates[$staffId] = array(
                'dept_id'         => $deptId,
                'complete_count'  => 0,
                'active_count'    => 0,
                'extend_count'    => 0,
                'failed_count'    => 0,
                'total_atem'      => 0,
                'total_incentive' => 0.0,
            );
        }
    }

    private function applyStatusCount(array &$aggregates, Collection $atems, string $field): void
    {
        foreach ($atems as $atem) {
            $involved = array();

            if ($atem->issuer_staff_id) {
                $involved[$atem->issuer_staff_id] = $atem->staff_dept_id;
            }

            foreach ($atem->arci as $member) {
                $involved[$member->staff_id] = $member->staff_dept_id;
            }

            foreach ($involved as $staffId => $deptId) {
                $this->ensureAggregate($aggregates, $staffId, $deptId);
                $aggregates[$staffId][$field] += 1;
                if ($deptId) {
                    $aggregates[$staffId]['dept_id'] = $deptId;
                }
            }
        }
    }

    private function applyEndOfMonthRemarks(int $month, int $year): void
    {
        $lastDay = Carbon::create($year, $month, 1)->endOfMonth()->day;
        if (Carbon::now()->day !== $lastDay || Carbon::now()->month !== $month) {
            return;
        }

        $this->info('End-of-month: checking for extended ATEMs...');

        $records = AtemBonusEligibility::where('month', $month)
            ->where('year', $year)
            ->whereNull('remark')
            ->get();

        foreach ($records as $record) {
            $extendedCount = Atem::with('arci')
                ->where('claimable', true)
                ->where('extension_count', '>', 0)
                ->whereMonth('closure_date', $month)
                ->whereYear('closure_date', $year)
                ->where(function ($q) use ($record) {
                    $q->where('issuer_staff_id', $record->staff_id)
                      ->orWhereHas('arci', function ($q2) use ($record) {
                          $q2->where('staff_id', $record->staff_id);
                      });
                })
                ->count();

            if ($extendedCount > 0) {
                $record->update(array('remark' => $extendedCount . ' ATEM(s) completed with extension'));
            }
        }
    }
}
