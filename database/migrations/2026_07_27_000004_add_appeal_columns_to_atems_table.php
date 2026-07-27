<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->text('appeal_remark')->nullable();
            $table->unsignedBigInteger('appealed_by')->nullable();
            $table->timestamp('appealed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn(['appeal_remark', 'appealed_by', 'appealed_at']);
        });
    }
};
