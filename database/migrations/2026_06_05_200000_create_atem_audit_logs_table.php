<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atem_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();
            $table->string('event', 80);
            $table->unsignedBigInteger('actor_staff_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('summary', 500)->nullable();
            $table->timestamps();

            $table->index(['atem_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_audit_logs');
    }
};
