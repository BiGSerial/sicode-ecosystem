<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\CoreAudit\CoreAuditAction;

enum LegacyProvisioningOutcome: string
{
    case Created = 'created';
    case AlreadyProvisioned = 'already_provisioned';
    case Updated = 'updated';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::Created,
            self::AlreadyProvisioned,
            self::Updated,
        ], true);
    }

    public function auditActionFor(string $entityType): CoreAuditAction
    {
        if ($this === self::Conflict) {
            return CoreAuditAction::LegacyProvisioningConflict;
        }

        if ($this === self::Rejected) {
            return CoreAuditAction::LegacyProvisioningRejected;
        }

        if ($this === self::Unavailable) {
            return CoreAuditAction::LegacyProvisioningUnavailable;
        }

        if ($entityType === 'organization') {
            return $this === self::AlreadyProvisioned
                ? CoreAuditAction::LegacyOrganizationAlreadyProvisioned
                : CoreAuditAction::LegacyOrganizationProvisioned;
        }

        return $this === self::AlreadyProvisioned
            ? CoreAuditAction::LegacyUserAlreadyProvisioned
            : CoreAuditAction::LegacyUserProvisioned;
    }
}
