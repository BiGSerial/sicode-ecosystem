<?php

declare(strict_types=1);

namespace App\CoreAudit;

enum CoreAuditAction: string
{
    case UserBlocked = 'USER_BLOCKED';
    case UserUnblocked = 'USER_UNBLOCKED';
    case UserDeactivated = 'USER_DEACTIVATED';
    case UserCanonicalNameChanged = 'USER_CANONICAL_NAME_CHANGED';
    case UserCanonicalEmailChanged = 'USER_CANONICAL_EMAIL_CHANGED';

    case LocalPasswordCredentialCreated = 'LOCAL_PASSWORD_CREDENTIAL_CREATED';
    case LocalPasswordCredentialChanged = 'LOCAL_PASSWORD_CREDENTIAL_CHANGED';
    case LocalPasswordCredentialDisabled = 'LOCAL_PASSWORD_CREDENTIAL_DISABLED';
    case LocalPasswordCredentialRehashed = 'LOCAL_PASSWORD_CREDENTIAL_REHASHED';

    case LocalAuthenticationSucceeded = 'LOCAL_AUTHENTICATION_SUCCEEDED';
    case LocalAuthenticationRejected = 'LOCAL_AUTHENTICATION_REJECTED';
    case LocalSessionEnded = 'LOCAL_SESSION_ENDED';

    case ExternalIdentityLinked = 'EXTERNAL_IDENTITY_LINKED';
    case ExternalIdentityRevoked = 'EXTERNAL_IDENTITY_REVOKED';
    case ExternalIdentityArchived = 'EXTERNAL_IDENTITY_ARCHIVED';
    case ExternalIdentityReconciled = 'EXTERNAL_IDENTITY_RECONCILED';

    case OrganizationCreated = 'ORGANIZATION_CREATED';
    case OrganizationSuspended = 'ORGANIZATION_SUSPENDED';
    case OrganizationReactivated = 'ORGANIZATION_REACTIVATED';
    case OrganizationDisabled = 'ORGANIZATION_DISABLED';

    case OrganizationMembershipCreated = 'ORGANIZATION_MEMBERSHIP_CREATED';
    case OrganizationMembershipActivated = 'ORGANIZATION_MEMBERSHIP_ACTIVATED';
    case OrganizationMembershipSuspended = 'ORGANIZATION_MEMBERSHIP_SUSPENDED';
    case OrganizationMembershipReactivated = 'ORGANIZATION_MEMBERSHIP_REACTIVATED';
    case OrganizationMembershipEnded = 'ORGANIZATION_MEMBERSHIP_ENDED';

    case ContractCreated = 'CONTRACT_CREATED';
    case ContractActivated = 'CONTRACT_ACTIVATED';
    case ContractSuspended = 'CONTRACT_SUSPENDED';
    case ContractReactivated = 'CONTRACT_REACTIVATED';
    case ContractEnded = 'CONTRACT_ENDED';

    case ApplicationCreated = 'APPLICATION_CREATED';
    case ApplicationDeactivated = 'APPLICATION_DEACTIVATED';

    case ApplicationClientCreated = 'APPLICATION_CLIENT_CREATED';
    case ApplicationClientDeactivated = 'APPLICATION_CLIENT_DEACTIVATED';

    case ApplicationContextCreated = 'APPLICATION_CONTEXT_CREATED';
    case ApplicationContextDeactivated = 'APPLICATION_CONTEXT_DEACTIVATED';
    case ApplicationEntryRequirementsChanged = 'APPLICATION_ENTRY_REQUIREMENTS_CHANGED';

    case ApplicationAccessGranted = 'APPLICATION_ACCESS_GRANTED';
    case ApplicationAccessRevoked = 'APPLICATION_ACCESS_REVOKED';
    case ApplicationAccessSuspended = 'APPLICATION_ACCESS_SUSPENDED';
    case ApplicationAccessReactivated = 'APPLICATION_ACCESS_REACTIVATED';

    case ContractApplicationGrantGranted = 'CONTRACT_APPLICATION_GRANT_GRANTED';
    case ContractApplicationGrantRevoked = 'CONTRACT_APPLICATION_GRANT_REVOKED';
    case ContractApplicationGrantSuspended = 'CONTRACT_APPLICATION_GRANT_SUSPENDED';
    case ContractApplicationGrantReactivated = 'CONTRACT_APPLICATION_GRANT_REACTIVATED';

    case ApplicationLaunchIssued = 'APPLICATION_LAUNCH_ISSUED';
    case ApplicationLaunchRejected = 'APPLICATION_LAUNCH_REJECTED';
    case ApplicationLaunchExchanged = 'APPLICATION_LAUNCH_EXCHANGED';
    case ApplicationLaunchExchangeRejected = 'APPLICATION_LAUNCH_EXCHANGE_REJECTED';
    case ApplicationLaunchReplayRejected = 'APPLICATION_LAUNCH_REPLAY_REJECTED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
