<?php

declare(strict_types=1);

namespace App\Models;

class LegacyProvisioningOperation extends CoreModel
{
    protected $fillable = [
        'target_application',
        'target_context',
        'entity_type',
        'entity_id',
        'organization_id',
        'idempotency_key_hash',
        'requested_at',
        'completed_at',
        'outcome',
        'attempt_count',
        'last_error_category',
        'remote_local_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }
}
