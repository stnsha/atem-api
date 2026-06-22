<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('atem_statuses')->where('value', 'Deleted')->exists()) {
            DB::table('atem_statuses')->insert([
                'value'               => 'Deleted',
                'description'         => 'ATEM card has been deleted by the issuer.',
                'system_action'       => 'Card is soft-deleted; visible only to grade 4, 5, and SuperAdmin.',
                'incentive_treatment' => 'Not eligible for incentive.',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('atem_statuses')->where('value', 'Deleted')->delete();
    }
};
