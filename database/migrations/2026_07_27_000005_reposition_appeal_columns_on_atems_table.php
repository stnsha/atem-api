<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The previous migration appended appeal_remark/appealed_by/appealed_at with
 * plain Schema::table() column adds, which MySQL places at the very end of
 * the table - after deleted_at/created_at/updated_at. Project convention is
 * that those three always stay last, so this repositions the appeal columns
 * back before them (right after pre_suspension_status_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE atems
            MODIFY COLUMN appeal_remark TEXT NULL AFTER pre_suspension_status_id,
            MODIFY COLUMN appealed_by BIGINT UNSIGNED NULL AFTER appeal_remark,
            MODIFY COLUMN appealed_at TIMESTAMP NULL AFTER appealed_by');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE atems
            MODIFY COLUMN appeal_remark TEXT NULL AFTER updated_at,
            MODIFY COLUMN appealed_by BIGINT UNSIGNED NULL AFTER appeal_remark,
            MODIFY COLUMN appealed_at TIMESTAMP NULL AFTER appealed_by');
    }
};
