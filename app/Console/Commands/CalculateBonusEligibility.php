<?php

namespace App\Console\Commands;

use App\Models\Atem;
use App\Models\AtemBonusEligibility;
use App\Services\StaffApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateBonusEligibility extends Command
{
    protected $signature = 'atem:calculate-bonus
                            {--month= : Month number (1-12), defaults to current month}
                            {--year=  : Year, defaults to current year}';

    protected $description = 'Calculate and upsert AtemBonusEligibility records for the given month/year';

    public function handle(StaffApiService $staffApi): int
    {
        $month = (int) ($this->option('month') ?: Carbon::now()->month);
        $year  = (int) ($this->option('year')  ?: Carbon::now()->year);

        $this->info("Calculating bonus eligibility for {$month}/{$year}...");

        $closedStatusIds = \App\Models\AtemStatus::whereIn('value', ['Completed', 'Completed with Excellence'])
            ->pluck('id');

        $atems = Atem::with(['arci', 'status'])
            ->whereIn('atem_status_id', $closedStatusIds)
            ->whereNotNull('closure_date')
            ->whereMonth('closure_date', $month)
            ->whereYear('closure_date', $year)
            ->get();

        if ($atems->isEmpty()) {
            $this->info('No completed ATEMs found for this period.');
            $this->applyEndOfMonthRemarks($month, $year);
            return 0;
        }

        // Aggregate per staff_id: total_atem count, total_incentive, dept_id
        $aggregates = array();

        foreach ($atems as $atem) {
            $rMembers = $atem->arci->where('role', 'R');
            $rCount   = $rMembers->count();

            // Collect all involved staff for this ATEM: issuer + all ARCI roles
            // Key: staff_id, value: ['dept_id', 'incentive']
            $involved = array();

            // Issuer with 0 incentive (may be overridden below if also ARCI)
            if ($atem->issuer_staff_id) {
                $involved[$atem->issuer_staff_id] = array(
                    'dept_id'   => $atem->staff_dept_id,
                    'incentive' => 0.0,
                );
            }

            // ARCI members — ARCI entry wins over issuer-only entry
            foreach ($atem->arci as $member) {
                $incentive = 0.0;
                if ($member->role === 'A') {
                    $incentive = (float) $atem->a_incentive_amount;
                } elseif ($member->role === 'R' && $rCount > 0) {
                    $incentive = (float) $atem->r_incentive_amount / $rCount;
                }

                // Override any existing entry for this staff_id (ARCI wins over issuer-only)
                $involved[$member->staff_id] = array(
                    'dept_id'   => $member->staff_dept_id,
                    'incentive' => $incentive,
                );
            }

            // Merge into aggregates
            foreach ($involved as $staffId => $data) {
                if (!isset($aggregates[$staffId])) {
                    $aggregates[$staffId] = array(
                        'dept_id'         => $data['dept_id'],
                        'total_atem'      => 0,
                        'total_incentive' => 0.0,
                    );
                }
                $aggregates[$staffId]['total_atem']      += 1;
                $aggregates[$staffId]['total_incentive'] += $data['incentive'];
                if ($data['dept_id']) {
                    $aggregates[$staffId]['dept_id'] = $data['dept_id'];
                }
            }
        }

        // Fetch grade and struct IDs via ODB API
        $staffIds  = array_keys($aggregates);
        $odbStaff  = $staffApi->getStaffInfo($staffIds);

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
