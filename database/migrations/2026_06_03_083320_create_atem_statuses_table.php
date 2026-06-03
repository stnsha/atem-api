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
        Schema::create('atem_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('value', 255);
            $table->longText('description');
            $table->string('system_action', 255);
            $table->string('incentive_treatment', 255);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atem_statuses');
    }
};