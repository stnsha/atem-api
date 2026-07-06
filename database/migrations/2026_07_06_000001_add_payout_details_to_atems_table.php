<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->string('payout_status')->nullable()->after('remarks');
            $table->text('payout_remark')->nullable()->after('payout_status');
            $table->unsignedBigInteger('payout_updated_by')->nullable()->after('payout_remark');
            $table->timestamp('payout_updated_at')->nullable()->after('payout_updated_by');
            $table->unsignedBigInteger('payout_closed_by')->nullable()->after('payout_updated_at');
            $table->timestamp('payout_closed_at')->nullable()->after('payout_closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('atems', function (Blueprint $table) {
            $table->dropColumn([
                'payout_status',
                'payout_remark',
                'payout_updated_by',
                'payout_updated_at',
                'payout_closed_by',
                'payout_closed_at',
            ]);
        });
    }
};
