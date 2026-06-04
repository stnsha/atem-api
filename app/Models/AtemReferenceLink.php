<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtemReferenceLink extends Model
{
    use HasFactory;

    protected $table = 'atem_reference_links';

    protected $fillable = [
        'atem_id',
        'name',
        'url',
        'added_by',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
