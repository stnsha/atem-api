<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('atems', function (Blueprint $table) {
            $table->id();

            // Card identity
            $table->string('title');
            $table->longText('description')->nullable();
            // Names are resolved on the odb frontend via these ids (the odb
            // staff / staff_department tables); no name snapshot is stored here.
            $table->unsignedBigInteger('issuer_staff_id')->nullable();
            $table->unsignedBigInteger('staff_dept_id')->nullable();

            // Classification and incentive configuration
            $table->foreignId('level_structure_id')->nullable()->constrained('level_structures');
            $table->foreignId('incentive_rule_id')->nullable()->constrained('incentive_rules');
            $table->double('base_incentive', 10, 2)->default(0);

            // Timeline
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_extended')->default(false);
            $table->date('extended_date_1')->nullable();
            $table->date('extended_date_2')->nullable();
            $table->unsignedTinyInteger('extension_count')->default(0);
            $table->date('final_due_date')->nullable();
            $table->date('closure_date')->nullable();

            // Closure
            $table->foreignId('atem_status_id')->nullable()->constrained('atem_statuses');
            $table->text('failure_reason')->nullable();
            $table->text('excellence_remark')->nullable();
            $table->text('remarks')->nullable();

            // Incentive result
            $table->double('a_incentive_amount', 10, 2)->default(0);
            $table->double('r_incentive_amount', 10, 2)->default(0);
            $table->double('total_incentive_amount', 10, 2)->default(0);
            $table->boolean('claimable')->default(false);

            // Lifecycle is tracked via atem_status_id (Draft / On Hold / In
            // Progress / Completed / ...). No separate record_state column.

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atems');
    }
};