<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per target staff each time their data is included in a
        // Staff Performance export (single-staff, bulk performance, or the
        // automatic payout lock summary). Written and read by the odb
        // frontend via /api/atem/export-logs; staff ids are ODB staff.id FKs,
        // names are resolved on the frontend.
        Schema::create('atem_export_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('target_staff_id');
            $table->unsignedInteger('actor_staff_id');
            $table->string('export_type', 32);
            $table->timestamp('exported_at')->useCurrent();

            $table->index(['target_staff_id', 'exported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_export_logs');
    }
};
