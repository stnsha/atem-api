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
        'department_id',
        'role',
        'assigned_by',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
