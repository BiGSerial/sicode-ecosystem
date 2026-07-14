<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

use App\LocalPassword\LocalPasswordVerificationReason;
use App\LocalPassword\VerifyLocalPasswordCredential;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;

final class AuthenticateLocalUser
{
    public function __construct(
        private readonly LocalLoginIdentifierNormalizer $identifierNormalizer,
        private readonly VerifyLocalPasswordCredential $verifyLocalPasswordCredential,
        private readonly LocalAuthenticationDummyHash $dummyHash,
        private readonly Hasher $hasher,
    ) {}

    public function __invoke(
        string $identifier,
        #[\SensitiveParameter] string $plainPassword,
    ): LocalAuthenticationDecision {
        $normalizedIdentifier = $this->identifierNormalizer->normalize($identifier);

        $user = User::query()
            ->where('primary_email_normalized', $normalizedIdentifier)
            ->first();

        if (! $user instanceof User) {
            $this->hasher->check($plainPassword, $this->dummyHash->hash());

            return LocalAuthenticationDecision::denied(LocalAuthenticationReason::InvalidCredentials);
        }

        if ($user->status !== 'active') {
            return LocalAuthenticationDecision::denied(LocalAuthenticationReason::UserNotActive);
        }

        $verification = ($this->verifyLocalPasswordCredential)($user, $plainPassword);

        if ($verification->verified) {
            return LocalAuthenticationDecision::authenticated($user, $verification->requiresRehash);
        }

        return LocalAuthenticationDecision::denied(match ($verification->reason) {
            LocalPasswordVerificationReason::CredentialNotActive => LocalAuthenticationReason::LocalCredentialNotActive,
            LocalPasswordVerificationReason::CredentialNotFound,
            LocalPasswordVerificationReason::PasswordMismatch,
            LocalPasswordVerificationReason::Verified => LocalAuthenticationReason::InvalidCredentials,
        });
    }
}
