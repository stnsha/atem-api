<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atem_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();
            // outlet.id lives in the odb database, not here - no FK constraint,
            // resolved to an outlet code on the odb frontend by id (same pattern
            // as issuer_staff_id / staff_dept_id on atems).
            $table->unsignedBigInteger('outlet_id');
            $table->timestamps();

            $table->unique(['atem_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_outlets');
    }
};
