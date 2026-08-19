<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// okr_id previously stored the OKR card id (many ATEMs sharing one card id,
// no way to tell which Key Result an ATEM belonged to). Renamed to reflect
// the corrected, more specific relationship: this now stores a single
// okr_key_results row id, set at the same granularity as the KR-side
// okr_key_results.atem_id back-reference. Existing values are NOT card ids
// converted to KR ids by this migration - see the reconciliation command
// (App\Console\Commands\ReconcileOkrKeyResultLinks) for backfilling/nulling
// out pre-existing rows against the correct KR ids.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->renameColumn('okr_id', 'okr_key_result_id');
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->renameColumn('okr_key_result_id', 'okr_id');
        });
    }
};
