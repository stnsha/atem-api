<?php

namespace App\Services;

use App\Models\AtemAuditLog;

class AtemAuditLogger
{
    public static function log(
        int $atemId,
        string $event,
        ?int $actorId,
        ?string $summary = null,
        ?array $changes = null
    ): void {
        AtemAuditLog::create([
            'atem_id'        => $atemId,
            'event'          => $event,
            'actor_staff_id' => $actorId,
            'summary'        => $summary,
            'changes'        => $changes,
        ]);
    }
}
