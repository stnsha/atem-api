<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            // Outlet-type ATEMs only. Both are configured up front (fixed set:
            // RM50/RM100/RM200); which one applies is decided by the closing
            // status - see AtemController::update().
            $table->double('reward_amount', 10, 2)->nullable()->after('reward_mechanism_id');
            $table->double('deduction_amount', 10, 2)->nullable()->after('reward_amount');
            // Signed result once the card reaches a closing status: +reward_amount,
            // -deduction_amount, or 0 while still open. Mirrors final_incentive_amount.
            $table->double('final_amount', 10, 2)->default(0)->after('deduction_amount');
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn(['reward_amount', 'deduction_amount', 'final_amount']);
        });
    }
};
