<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtemReferenceLink extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'atem_reference_links';

    protected $fillable = [
        'atem_id',
        'name',
        'url',
        'added_by',
        'is_reference_outcome',
    ];

    protected $casts = [
        'is_reference_outcome' => 'boolean',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
