<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            // Outlet-type ATEMs only: the free-text label selected from the Reward
            // Masterlist at creation (e.g. "Free Ticket", "Ice Cream") - purely
            // descriptive, not fed into reward_amount/final_amount/forecast math.
            $table->string('reward_label', 255)->nullable()->after('final_amount');
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn('reward_label');
        });
    }
};
