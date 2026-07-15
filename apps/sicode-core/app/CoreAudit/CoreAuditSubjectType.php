<?php

declare(strict_types=1);

namespace App\CoreAudit;

enum CoreAuditSubjectType: string
{
    case User = 'USER';
    case LocalPasswordCredential = 'LOCAL_PASSWORD_CREDENTIAL';
    case LocalAuthenticationAttempt = 'LOCAL_AUTHENTICATION_ATTEMPT';
    case ExternalIdentity = 'EXTERNAL_IDENTITY';
    case Organization = 'ORGANIZATION';
    case OrganizationMembership = 'ORGANIZATION_MEMBERSHIP';
    case Contract = 'CONTRACT';
    case Application = 'APPLICATION';
    case ApplicationClient = 'APPLICATION_CLIENT';
    case ApplicationContext = 'APPLICATION_CONTEXT';
    case ApplicationAccess = 'APPLICATION_ACCESS';
    case ContractApplicationGrant = 'CONTRACT_APPLICATION_GRANT';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
