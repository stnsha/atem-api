<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtemArci extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'atem_arci';

    protected $fillable = [
        'atem_id',
        'staff_id',
        'staff_dept_id',
        'role',
        'is_incentivised',
        'assigned_by',
    ];

    protected $casts = [
        'is_incentivised' => 'boolean',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
