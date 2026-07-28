<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Reward Masterlist entries are not necessarily monetary (e.g. "Free Ticket",
// "Ice Cream"), so reward_value must hold free text, not a decimal amount.
// doctrine/dbal isn't installed, so this uses a raw ALTER instead of
// Schema::table()->...->change().
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reward_masterlist MODIFY reward_value VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reward_masterlist MODIFY reward_value DECIMAL(10,2) NOT NULL');
    }
};
