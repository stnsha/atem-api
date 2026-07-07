<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atem_area_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();
            // staff.id lives in the odb database, not here - no FK constraint,
            // resolved to a staff name/position on the odb frontend by id (same
            // pattern as outlet_id on atem_outlets).
            $table->unsignedBigInteger('staff_id');
            $table->timestamps();

            $table->unique(['atem_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_area_managers');
    }
};
