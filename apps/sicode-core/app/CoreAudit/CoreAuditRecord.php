<?php

declare(strict_types=1);

namespace App\CoreAudit;

use Carbon\CarbonInterface;

final readonly class CoreAuditRecord
{
    /**
     * @param  array<array-key, mixed>|null  $details
     */
    public function __construct(
        public CarbonInterface $occurredAt,
        public CoreAuditActorType $actorType,
        public ?string $actorId,
        public CoreAuditAction $action,
        public CoreAuditSubjectType $subjectType,
        public string $subjectId,
        public ?string $applicationId = null,
        public ?string $contextId = null,
        public ?string $reason = null,
        public ?string $correlationId = null,
        public ?array $details = null,
    ) {}
}
