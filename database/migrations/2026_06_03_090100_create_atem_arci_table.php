<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('atem_arci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();

            // Staff identity is sourced from the odb database. Only the foreign
            // ids are stored; names are resolved on the odb frontend by id.
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('staff_dept_id')->nullable();

            $table->enum('role', ['A', 'R', 'C', 'I']);
            $table->unsignedBigInteger('assigned_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['atem_id', 'staff_id']);
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atem_arci');
    }
};
