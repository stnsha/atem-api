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
            $table->unsignedBigInteger('pre_suspension_status_id')->nullable()->after('suspended_by');
        });

        $suspendedStatusId = DB::table('atem_statuses')
            ->where('value', 'Suspended')
            ->whereNull('deleted_at')
            ->value('id');

        if (!$suspendedStatusId) {
            return;
        }

        $suspendedAtems = DB::table('atems')
            ->where('atem_status_id', $suspendedStatusId)
            ->get();

        foreach ($suspendedAtems as $atem) {
            $logs = DB::table('atem_audit_logs')
                ->where('atem_id', $atem->id)
                ->where('event', 'status_changed')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($logs as $log) {
                $changes = json_decode($log->changes, true);
                if (!is_array($changes)) {
                    continue;
                }
                foreach ($changes as $change) {
                    if (
                        isset($change['field'], $change['to']) &&
                        $change['field'] === 'atem_status_id' &&
                        $change['to'] === 'Suspended' &&
                        !empty($change['from'])
                    ) {
                        $preStatusId = DB::table('atem_statuses')
                            ->where('value', $change['from'])
                            ->whereNull('deleted_at')
                            ->value('id');

                        if ($preStatusId) {
                            DB::table('atems')
                                ->where('id', $atem->id)
                                ->update(array('pre_suspension_status_id' => $preStatusId));
                        }
                        break 2;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn('pre_suspension_status_id');
        });
    }
};
