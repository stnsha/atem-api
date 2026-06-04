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
        Schema::create('atem_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atem_id')->constrained('atems')->cascadeOnDelete();

            // A stored upload. name is the original filename for display; the file
            // bytes are held in the DB as a base64 string (no files on disk).
            $table->string('name');
            $table->string('type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->longText('content');

            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atem_attachments');
    }
};
