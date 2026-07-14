<?php

declare(strict_types=1);

namespace App\ApplicationEntry;

enum ApplicationEntryReason: string
{
    case Allowed = 'ALLOWED';
    case UserNotActive = 'USER_NOT_ACTIVE';
    case ApplicationNotActive = 'APPLICATION_NOT_ACTIVE';
    case ContextRequired = 'CONTEXT_REQUIRED';
    case ContextNotActive = 'CONTEXT_NOT_ACTIVE';
    case ContextApplicationMismatch = 'CONTEXT_APPLICATION_MISMATCH';
    case ApplicationAccessNotGranted = 'APPLICATION_ACCESS_NOT_GRANTED';
    case ApplicationAccessNotEffective = 'APPLICATION_ACCESS_NOT_EFFECTIVE';
    case OrganizationRequired = 'ORGANIZATION_REQUIRED';
    case OrganizationMembershipNotEffective = 'ORGANIZATION_MEMBERSHIP_NOT_EFFECTIVE';
    case OrganizationMembershipAmbiguous = 'ORGANIZATION_MEMBERSHIP_AMBIGUOUS';
    case ContractRequired = 'CONTRACT_REQUIRED';
    case ContractNotEffective = 'CONTRACT_NOT_EFFECTIVE';
    case ContractApplicationGrantNotEffective = 'CONTRACT_APPLICATION_GRANT_NOT_EFFECTIVE';
}
