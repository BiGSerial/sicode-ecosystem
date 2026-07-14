<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

enum LocalAuthenticationReason: string
{
    case Authenticated = 'AUTHENTICATED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case UserNotActive = 'USER_NOT_ACTIVE';
    case LocalCredentialNotActive = 'LOCAL_CREDENTIAL_NOT_ACTIVE';
}
