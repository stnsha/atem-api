<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atem_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_staff_id');
            $table->text('message');
            $table->timestamps();

            $table->index(['atem_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_messages');
    }
};
