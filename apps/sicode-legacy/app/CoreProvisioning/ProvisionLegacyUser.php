<?php

namespace App\CoreProvisioning;

use App\Models\CoreIdentityLink;
use App\Models\CoreOrganizationLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProvisionLegacyUser
{
    public function __construct(
        private readonly EnsureProvisioningRuntime $runtime,
        private readonly ProvisioningLock $lock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload, string $clientIdentifier, string $applicationContext): ProvisioningOutcome
    {
        $this->runtime->assertEnabled();

        if (($payload['status'] ?? null) !== 'active') {
            throw new ProvisioningRejected('UNSUPPORTED_USER_STATUS');
        }

        $coreIssuer = (string) $payload['core_issuer'];
        $coreSubject = (string) $payload['core_subject'];
        $coreOrganizationId = (string) $payload['core_organization_id'];
        $name = (string) $payload['name'];
        $email = isset($payload['email']) ? (string) $payload['email'] : null;
        $idempotencyKey = (string) $payload['idempotency_key'];

        $lockKey = implode(':', [
            'core-provisioning-user',
            strtolower($applicationContext),
            $clientIdentifier,
            $coreSubject,
            $idempotencyKey,
        ]);

        return $this->lock->withLock($lockKey, function () use ($coreIssuer, $coreSubject, $coreOrganizationId, $applicationContext, $name, $email): ProvisioningOutcome {
            return DB::transaction(function () use ($coreIssuer, $coreSubject, $coreOrganizationId, $applicationContext, $name, $email): ProvisioningOutcome {
                /** @var CoreOrganizationLink|null $organizationLink */
                $organizationLink = CoreOrganizationLink::query()
                    ->where('core_issuer', $coreIssuer)
                    ->where('core_organization_id', $coreOrganizationId)
                    ->where('application_context', $applicationContext)
                    ->where('status', CoreOrganizationLink::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->first();

                if (! $organizationLink instanceof CoreOrganizationLink || ! $organizationLink->company || $organizationLink->company->trashed()) {
                    throw new ProvisioningRejected('ORGANIZATION_LINK_REQUIRED');
                }

                $links = CoreIdentityLink::query()
                    ->where('core_issuer', $coreIssuer)
                    ->where('core_subject', $coreSubject)
                    ->where('application_context', $applicationContext)
                    ->limit(2)
                    ->lockForUpdate()
                    ->get();

                if ($links->count() > 1) {
                    throw new ProvisioningConflict('DUPLICATE_IDENTITY_LINK');
                }

                $existing = $links->first();

                if ($existing instanceof CoreIdentityLink) {
                    if ($existing->status !== CoreIdentityLink::STATUS_ACTIVE) {
                        throw new ProvisioningConflict('IDENTITY_LINK_NOT_ACTIVE');
                    }

                    if (! $existing->user instanceof User || $existing->user->trashed()) {
                        throw new ProvisioningConflict('USER_UNAVAILABLE');
                    }

                    $user = $existing->user;

                    if ($user->company_id !== null && $user->company_id !== $organizationLink->company_id) {
                        throw new ProvisioningConflict('USER_COMPANY_CONFLICT');
                    }

                    if ($email !== null && $email !== '' && $email !== $user->email) {
                        $emailInUse = User::query()
                            ->where('email', $email)
                            ->where('id', '!=', $user->id)
                            ->exists();

                        if ($emailInUse) {
                            throw new ProvisioningConflict('EMAIL_ALREADY_IN_USE');
                        }
                    }

                    $user->forceFill([
                        'name' => $name,
                        'email' => $email !== null && $email !== '' ? $email : $user->email,
                        'company_id' => $user->company_id ?? $organizationLink->company_id,
                    ]);

                    $result = $user->isDirty() ? ProvisioningOutcome::RESULT_UPDATED : ProvisioningOutcome::RESULT_ALREADY_PROVISIONED;

                    if ($user->isDirty()) {
                        $user->save();
                    }

                    return new ProvisioningOutcome($result, 'user', [
                        'core_subject' => $coreSubject,
                        'core_organization_id' => $coreOrganizationId,
                        'user_id' => $user->id,
                        'company_id' => $organizationLink->company_id,
                    ]);
                }

                $finalEmail = $email !== null && $email !== ''
                    ? $email
                    : $this->userPlaceholderEmail($coreSubject);

                $existingUserByEmail = User::withTrashed()->where('email', $finalEmail)->first();

                if ($existingUserByEmail instanceof User) {
                    throw new ProvisioningConflict('EMAIL_ALREADY_EXISTS_WITHOUT_LINK');
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $finalEmail,
                    'password' => $this->randomLocalPassword(),
                    'company_id' => $organizationLink->company_id,
                    'first_pass' => true,
                ]);

                CoreIdentityLink::create([
                    'core_issuer' => $coreIssuer,
                    'core_subject' => $coreSubject,
                    'legacy_user_id' => $user->id,
                    'application_context' => $applicationContext,
                    'status' => CoreIdentityLink::STATUS_ACTIVE,
                    'linked_at' => now(),
                ]);

                return new ProvisioningOutcome(ProvisioningOutcome::RESULT_CREATED, 'user', [
                    'core_subject' => $coreSubject,
                    'core_organization_id' => $coreOrganizationId,
                    'user_id' => $user->id,
                    'company_id' => $organizationLink->company_id,
                ]);
            });
        });
    }

    private function userPlaceholderEmail(string $coreSubject): string
    {
        $domain = (string) config('core_provisioning.placeholder_email_domain', 'provisioning.local');
        $compactUuid = str_replace('-', '', strtolower($coreSubject));

        return 'core-user-'.$compactUuid.'@'.$domain;
    }

    private function randomLocalPassword(): string
    {
        return bin2hex(random_bytes(32));
    }
}
