<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtemAuditLog extends Model
{
    use SoftDeletes;

    protected $table = 'atem_audit_logs';

    protected $fillable = [
        'atem_id',
        'event',
        'actor_staff_id',
        'changes',
        'summary',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
