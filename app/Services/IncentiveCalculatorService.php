<?php

namespace App\Services;

use App\Models\IncentiveRule;
use App\Models\LevelStructure;

/**
 * Single source of truth for ATEM incentive calculation, implementing the
 * finalised CEO business rules (Revised ATEM Incentive System v3):
 *
 *  - Base incentive is derived from the ATEM level
 *    (Level 1 = 0, Level 2 = 100, Level 3 = 200, Level 4 = 300).
 *  - Rule 1 ("Only A 100%"): A = base, R = 0.
 *  - Rule 2 ("A 100%, R 50%"): A = base, R = 50% of base.
 *  - C and I never receive incentive.
 *  - Incentive is claimable only when the closure status is Complete or
 *    Complete with Excellence, and the level carries a non-zero base.
 */
class IncentiveCalculatorService
{
    const STATUS_COMPLETED = 'Completed';
    const STATUS_EXCELLENCE = 'Completed with Excellence';

    /**
     * @return array{base: float, a: float, r: float, total: float, claimable: bool}
     */
    public function calculate(
        ?LevelStructure $level,
        ?IncentiveRule $rule,
        ?string $statusValue = null
    ): array {
        $base = $level ? (float) $level->incentive_value : 0.0;

        $a = 0.0;
        $r = 0.0;

        if ($base > 0 && $rule) {
            // A always receives the full base when a rule is applied.
            $a = $base;
            $r = $this->responsibleFactor($rule) * $base;
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

    /**
     * Fraction of the base incentive paid to the Responsible (R) person.
     * Rule 2 pays R 50%; every other rule pays R nothing.
     */
    private function responsibleFactor(IncentiveRule $rule): float
    {
        return strcasecmp(trim($rule->code), 'Rule 2') === 0 ? 0.5 : 0.0;
    }
}
