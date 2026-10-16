<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtemExportLog extends Model
{
    protected $table = 'atem_export_logs';

    public $timestamps = false;

    protected $fillable = [
        'target_staff_id',
        'actor_staff_id',
        'export_type',
        'exported_at',
    ];

    protected $casts = [
        'target_staff_id' => 'integer',
        'actor_staff_id'  => 'integer',
        'exported_at'     => 'datetime:Y-m-d H:i:s',
    ];
}
