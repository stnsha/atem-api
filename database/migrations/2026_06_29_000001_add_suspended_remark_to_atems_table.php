<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->text('suspended_remark')->nullable()->after('remarks');
        });

        $suspendedStatusId = DB::table('atem_statuses')
            ->where('value', 'Suspended')
            ->whereNull('deleted_at')
            ->value('id');

        if ($suspendedStatusId) {
            DB::table('atems')
                ->where('atem_status_id', $suspendedStatusId)
                ->whereNotNull('remarks')
                ->update(array(
                    'suspended_remark' => DB::raw('`remarks`'),
                    'remarks'          => null,
                ));
        }
    }

    public function down(): void
    {
        $suspendedStatusId = DB::table('atem_statuses')
            ->where('value', 'Suspended')
            ->whereNull('deleted_at')
            ->value('id');

        if ($suspendedStatusId) {
            DB::table('atems')
                ->where('atem_status_id', $suspendedStatusId)
                ->whereNotNull('suspended_remark')
                ->update(array(
                    'remarks'          => DB::raw('`suspended_remark`'),
                ));
        }

        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn('suspended_remark');
        });
    }
};
