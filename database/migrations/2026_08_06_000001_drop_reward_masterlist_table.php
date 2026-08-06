<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reward Masterlist feature removed - Outlet ATEM reward is now a free-text
        // field on atems.reward_label (already a plain nullable VARCHAR, no FK to
        // this table), entered directly by the issuer instead of picked from a
        // SuperAdmin-managed list.
        Schema::dropIfExists('reward_masterlist');
    }

    public function down(): void
    {
        Schema::create('reward_masterlist', function (Blueprint $table) {
            $table->id();
            $table->string('reward_value', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
