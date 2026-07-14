<?php

declare(strict_types=1);

namespace App\LocalPassword;

enum LocalPasswordVerificationReason: string
{
    case Verified = 'VERIFIED';
    case CredentialNotFound = 'CREDENTIAL_NOT_FOUND';
    case CredentialNotActive = 'CREDENTIAL_NOT_ACTIVE';
    case PasswordMismatch = 'PASSWORD_MISMATCH';
}
