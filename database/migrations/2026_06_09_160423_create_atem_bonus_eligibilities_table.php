<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atem_bonus_eligibilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('staff_dept_id')->nullable();
            $table->unsignedBigInteger('staff_grade')->nullable();
            $table->unsignedBigInteger('staff_struct')->nullable();
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->integer('total_atem')->default(0);
            $table->double('total_incentive', 10, 2)->default(0.00);
            $table->string('remark')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atem_bonus_eligibilities');
    }
};
