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
        Schema::create('level_structures', function (Blueprint $table) {
            $table->id();
            $table->string('level');
            $table->string('system_name');
            $table->double('incentive_value', 10, 2);
            $table->longText('business_meaning');
            $table->longText('claim_treatment');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('level_structures');
    }
};