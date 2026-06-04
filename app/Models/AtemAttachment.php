<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtemAttachment extends Model
{
    use HasFactory;

    protected $table = 'atem_attachments';

    protected $fillable = [
        'atem_id',
        'name',
        'type',
        'size',
        'content',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // The base64 payload is large and must never leak into list/JSON responses.
    protected $hidden = [
        'content',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
