<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn(['failure_reason', 'excellence_remark']);
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->string('failure_reason')->nullable();
            $table->string('excellence_remark')->nullable();
        });
    }
};
