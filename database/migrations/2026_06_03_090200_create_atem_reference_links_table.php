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
        Schema::create('atem_reference_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();

            // A named external link supplied by the issuer. Replaces the former
            // single google_link column on the atems table.
            $table->string('name');
            $table->string('url', 1000);

            $table->unsignedBigInteger('added_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atem_reference_links');
    }
};
