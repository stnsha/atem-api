<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AtemAuditLog;
use App\Models\AtemProgress;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'issuer_staff_id',
        'staff_dept_id',
        'level_structure_id',
        'incentive_rule_id',
        'base_incentive',
        'start_date',
        'end_date',
        'is_extended',
        'extended_date_1',
        'extension_count',
        'final_due_date',
        'closure_date',
        'atem_status_id',
        'remarks',
        'a_incentive_amount',
        'r_incentive_amount',
        'total_incentive_amount',
        'final_incentive_amount',
        'claimable',
        'incentive_approved',
        'created_by',
        'updated_by',
        'closed_by',
    ];

    protected $casts = [
        'base_incentive'         => 'float',
        'a_incentive_amount'     => 'float',
        'r_incentive_amount'     => 'float',
        'total_incentive_amount'  => 'float',
        'final_incentive_amount'  => 'float',
        'is_extended'             => 'boolean',
        'claimable'               => 'boolean',
        'incentive_approved'      => 'boolean',
        'extension_count'         => 'integer',
        'start_date'              => 'date:Y-m-d',
        'end_date'                => 'date:Y-m-d',
        'extended_date_1'         => 'date:Y-m-d',
        'final_due_date'          => 'date:Y-m-d',
        'closure_date'            => 'date:Y-m-d',
    ];

    public function arci(): HasMany
    {
        return $this->hasMany(AtemArci::class);
    }

    public function referenceLinks(): HasMany
    {
        return $this->hasMany(AtemReferenceLink::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AtemAttachment::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(AtemProgress::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AtemAuditLog::class);
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
