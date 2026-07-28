<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RewardMasterlist extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reward_masterlist';

    protected $fillable = [
        'reward_value',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
