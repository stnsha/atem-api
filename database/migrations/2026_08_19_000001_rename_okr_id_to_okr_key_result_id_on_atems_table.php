<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// okr_id previously stored the OKR card id (many ATEMs sharing one card id,
// no way to tell which Key Result an ATEM belonged to). Renamed to reflect
// the corrected, more specific relationship: this now stores a single
// okr_key_results row id, set at the same granularity as the KR-side
// okr_key_results.atem_id back-reference. Existing values are NOT card ids
// converted to KR ids by this migration - see the reconciliation command
// (App\Console\Commands\ReconcileOkrKeyResultLinks) for backfilling/nulling
// out pre-existing rows against the correct KR ids.
//
// Uses raw CHANGE COLUMN instead of Schema::renameColumn() - that emits
// native RENAME COLUMN syntax, which requires MySQL 8.0.3+/MariaDB 10.5.2+
// (or the doctrine/dbal package as a fallback on older versions, not
// installed here) and fails with a syntax error on older MariaDB.
// CHANGE COLUMN works on every MySQL/MariaDB version.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `atems` CHANGE COLUMN `okr_id` `okr_key_result_id` INT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `atems` CHANGE COLUMN `okr_key_result_id` `okr_id` INT UNSIGNED NULL DEFAULT NULL');
    }
};
