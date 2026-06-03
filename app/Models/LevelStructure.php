<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LevelStructure extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'level',
        'system_name',
        'incentive_value',
        'business_meaning',
        'claim_treatment',
    ];
}