<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AtemMessage extends Model
{
    use SoftDeletes;

    protected $table = 'atem_messages';

    protected $fillable = [
        'atem_id',
        'sender_staff_id',
        'message',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }
}
