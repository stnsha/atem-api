<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atem_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_staff_id');
            $table->string('type', 40);
            $table->foreignId('atem_id')->nullable()->constrained('atems')->nullOnDelete();
            $table->foreignId('atem_message_id')->nullable()->constrained('atem_messages')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_staff_id', 'read_at']);
            $table->index(['recipient_staff_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_notifications');
    }
};
