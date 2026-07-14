<?php

declare(strict_types=1);

namespace App\LocalPassword;

use App\Models\LocalPasswordCredential;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use RuntimeException;

final class VerifyLocalPasswordCredential
{
    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    public function __invoke(User $user, #[\SensitiveParameter] string $plainPassword): LocalPasswordVerification
    {
        $credential = LocalPasswordCredential::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $credential instanceof LocalPasswordCredential) {
            return LocalPasswordVerification::denied(LocalPasswordVerificationReason::CredentialNotFound);
        }

        if (! $credential->isActive()) {
            return LocalPasswordVerification::denied(LocalPasswordVerificationReason::CredentialNotActive);
        }

        try {
            if (! $this->hasher->check($plainPassword, $credential->password_hash)) {
                return LocalPasswordVerification::denied(LocalPasswordVerificationReason::PasswordMismatch);
            }
        } catch (RuntimeException) {
            return LocalPasswordVerification::denied(LocalPasswordVerificationReason::PasswordMismatch);
        }

        return LocalPasswordVerification::verified($this->hasher->needsRehash($credential->password_hash));
    }
}
