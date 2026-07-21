<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

enum LegacyProvisioningErrorCategory: string
{
    case ConfigurationInvalid = 'configuration_invalid';
    case LocalValidationFailed = 'local_validation_failed';
    case AuthenticationRejected = 'authentication_rejected';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
    case InvalidResponse = 'invalid_response';
    case Timeout = 'timeout';
    case ConnectionFailed = 'connection_failed';
    case HttpUnavailable = 'http_unavailable';
}
