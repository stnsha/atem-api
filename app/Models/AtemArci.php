<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtemArci extends Model
{
    use HasFactory;

    protected $table = 'atem_arci';

    protected $fillable = [
        'atem_id',
        'staff_id',
        'staff_name',
        'department_id',
        'department_name',
        'role',
        'assigned_by',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
