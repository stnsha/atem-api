<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            // 1 = HQ (level_structure_id / incentive_rule_id apply), 2 = Outlet
            // (pillar_id / reward_mechanism_id apply). Existing rows default to
            // HQ; see AddAtemTypeToExistingAtemsSeeder for the explicit backfill.
            $table->unsignedTinyInteger('atem_type')->default(1)->after('staff_dept_id');

            // Outlet-only classification. No FK constraint yet: the pillar and
            // reward mechanism lookup tables don't exist yet (frontend dropdowns
            // are placeholder-only for now).
            $table->unsignedBigInteger('pillar_id')->nullable()->after('atem_type');
            $table->unsignedBigInteger('reward_mechanism_id')->nullable()->after('pillar_id');

            // Outlet's "Total Reward" is calculated separately from HQ's
            // Base/A/R incentive breakdown, so it gets its own column rather
            // than reusing total_incentive_amount.
            $table->double('total_reward_amount', 10, 2)->default(0)->after('reward_mechanism_id');
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn(['atem_type', 'pillar_id', 'reward_mechanism_id', 'total_reward_amount']);
        });
    }
};
