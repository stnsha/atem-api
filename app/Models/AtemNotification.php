<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtemNotification extends Model
{
    protected $table = 'atem_notifications';

    protected $fillable = [
        'recipient_staff_id',
        'type',
        'atem_id',
        'atem_message_id',
        'payload',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function atem(): BelongsTo
    {
        return $this->belongsTo(Atem::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AtemMessage::class, 'atem_message_id');
    }
}
