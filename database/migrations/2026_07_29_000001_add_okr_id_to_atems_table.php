<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->unsignedInteger('okr_id')->nullable()->after('atem_type');
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn('okr_id');
        });
    }
};
