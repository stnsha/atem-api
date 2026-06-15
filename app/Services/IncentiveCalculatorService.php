<?php

namespace App\Services;

use App\Models\IncentiveRule;
use App\Models\LevelStructure;

/**
 * Single source of truth for ATEM incentive calculation.
 *
 * Rules (codes must match incentive_rules.code exactly, case-insensitive):
 *   Rule 1 — A1 50%, A2 50%: each incentivised A receives 50% of base (max 2 A).
 *   Rule 2 — Only A1 100%: the one incentivised A receives 100% of base.
 *   Rule 3 — A1 100%, R pooled 50%: incentivised A receives 100%; incentivised
 *             R members share a pool equal to 50% of base (max 1 A, max 2 R).
 *   Rule 4 — A1 50%, A2 50%, R pooled 50%: each incentivised A gets 50%; R pool 50% (max 2 A, max 2 R).
 *   Rule 5 — A1 100%, R1 100%: A receives 100%; R receives 100% (max 1 A, max 1 R).
 *   Rule 6 — A1 50%, A2 50%, R1 100%: each A gets 50%; R receives 100% (max 2 A, max 1 R).
 *
 * C and I are never incentivised.
 * Claimable only when closed as Completed or Completed with Excellence and
 * the level carries a non-zero base.
 */
class IncentiveCalculatorService
{
    const STATUS_COMPLETED = 'Completed';
    const STATUS_EXCELLENCE = 'Completed with Excellence';

    /**
     * @param  LevelStructure|null  $level
     * @param  IncentiveRule|null   $rule
     * @param  string|null          $statusValue
     * @param  int                  $incentivisedACount  Number of A members marked is_incentivised
     * @param  int                  $incentivisedRCount  Number of R members marked is_incentivised
     * @return array{base: float, a: float, r: float, total: float, claimable: bool}
     */
    public function calculate(
        ?LevelStructure $level,
        ?IncentiveRule $rule,
        ?string $statusValue = null,
        int $incentivisedACount = 0,
        int $incentivisedRCount = 0
    ): array {
        $base = $level ? (float) $level->incentive_value : 0.0;
        $a = 0.0;
        $r = 0.0;

        if ($base > 0 && $rule) {
            $code = strtolower(trim($rule->code));

            if ($code === 'rule 1') {
                $a = $base * 0.5 * $incentivisedACount;
                $r = 0.0;
            } elseif ($code === 'rule 2') {
                $a = $base * $incentivisedACount;
                $r = 0.0;
            } elseif ($code === 'rule 3') {
                $a = $base * $incentivisedACount;
                $r = $incentivisedRCount > 0 ? $base * 0.5 : 0.0;
            } elseif ($code === 'rule 4') {
                $a = $base * 0.5 * $incentivisedACount;
                $r = $incentivisedRCount > 0 ? $base * 0.5 : 0.0;
            } elseif ($code === 'rule 5') {
                $a = $base * $incentivisedACount;
                $r = $base * $incentivisedRCount;
            } elseif ($code === 'rule 6') {
                $a = $base * 0.5 * $incentivisedACount;
                $r = $base * $incentivisedRCount;
            }
        }

        $total = $a + $r;

        $claimable = $base > 0 && in_array(
            $statusValue,
            [self::STATUS_COMPLETED, self::STATUS_EXCELLENCE],
            true
        );

        return [
            'base'      => round($base, 2),
            'a'         => round($a, 2),
            'r'         => round($r, 2),
            'total'     => round($total, 2),
            'claimable' => $claimable,
        ];
    }
}
