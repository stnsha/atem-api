<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atem_arci', function (Blueprint $table) {
            // outlet.id lives in the odb database, not here - no FK constraint,
            // resolved to an outlet code on the odb frontend by id (same
            // pattern as outlet_id on atem_outlets). Outlet-type ATEMs store
            // the outlet here instead of overloading staff_dept_id.
            $table->unsignedBigInteger('outlet_id')->nullable()->after('staff_dept_id');
        });
    }

    public function down(): void
    {
        Schema::table('atem_arci', function (Blueprint $table) {
            $table->dropColumn('outlet_id');
        });
    }
};
