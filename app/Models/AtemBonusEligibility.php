<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AtemBonusEligibility extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'staff_dept_id',
        'staff_grade',
        'staff_struct',
        'month',
        'year',
        'total_atem',
        'total_incentive',
        'remark',
    ];

    protected $casts = [
        'total_incentive' => 'float',
    ];
}
