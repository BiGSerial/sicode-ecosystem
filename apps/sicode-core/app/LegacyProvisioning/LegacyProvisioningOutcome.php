<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\CoreAudit\CoreAuditAction;

enum LegacyProvisioningOutcome: string
{
    case Created = 'created';
    case AlreadyProvisioned = 'already_provisioned';
    case Updated = 'updated';
    case Suspended = 'suspended';
    case AlreadySuspended = 'already_suspended';
    case Reactivated = 'reactivated';
    case AlreadyActive = 'already_active';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';

    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::Created,
            self::AlreadyProvisioned,
            self::Updated,
            self::Suspended,
            self::AlreadySuspended,
            self::Reactivated,
            self::AlreadyActive,
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
            return match ($this) {
                self::AlreadyProvisioned => CoreAuditAction::LegacyOrganizationAlreadyProvisioned,
                self::Suspended => CoreAuditAction::LegacyOrganizationSuspended,
                self::AlreadySuspended => CoreAuditAction::LegacyOrganizationAlreadySuspended,
                self::Reactivated => CoreAuditAction::LegacyOrganizationReactivated,
                self::AlreadyActive => CoreAuditAction::LegacyOrganizationAlreadyActive,
                default => CoreAuditAction::LegacyOrganizationProvisioned,
            };
        }

        return match ($this) {
            self::AlreadyProvisioned => CoreAuditAction::LegacyUserAlreadyProvisioned,
            self::Suspended => CoreAuditAction::LegacyUserSuspended,
            self::AlreadySuspended => CoreAuditAction::LegacyUserAlreadySuspended,
            self::Reactivated => CoreAuditAction::LegacyUserReactivated,
            self::AlreadyActive => CoreAuditAction::LegacyUserAlreadyActive,
            default => CoreAuditAction::LegacyUserProvisioned,
        };
    }
}
