<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'google_link',
        'issuer_staff_id',
        'issuer_name',
        'department_id',
        'department_name',
        'level_structure_id',
        'incentive_rule_id',
        'base_incentive',
        'start_date',
        'end_date',
        'is_extended',
        'extended_date_1',
        'extended_date_2',
        'extension_count',
        'final_due_date',
        'closure_date',
        'atem_status_id',
        'failure_reason',
        'excellence_remark',
        'remarks',
        'a_incentive_amount',
        'r_incentive_amount',
        'total_incentive_amount',
        'claimable',
        'record_state',
        'created_by',
        'updated_by',
        'closed_by',
    ];

    protected $casts = [
        'base_incentive'         => 'float',
        'a_incentive_amount'     => 'float',
        'r_incentive_amount'     => 'float',
        'total_incentive_amount' => 'float',
        'is_extended'            => 'boolean',
        'claimable'              => 'boolean',
        'extension_count'        => 'integer',
        'start_date'             => 'date',
        'end_date'               => 'date',
        'extended_date_1'        => 'date',
        'extended_date_2'        => 'date',
        'final_due_date'         => 'date',
        'closure_date'           => 'date',
    ];

    public function arci(): HasMany
    {
        return $this->hasMany(AtemArci::class);
    }

    public function levelStructure(): BelongsTo
    {
        return $this->belongsTo(LevelStructure::class);
    }

    public function incentiveRule(): BelongsTo
    {
        return $this->belongsTo(IncentiveRule::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AtemStatus::class, 'atem_status_id');
    }
}
