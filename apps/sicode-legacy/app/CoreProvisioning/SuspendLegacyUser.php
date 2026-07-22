<?php

namespace App\CoreProvisioning;

use App\Models\CoreIdentityLink;
use Illuminate\Support\Facades\DB;

final class SuspendLegacyUser
{
    public function __construct(
        private readonly EnsureProvisioningRuntime $runtime,
        private readonly ProvisioningLock $lock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(string $coreSubject, array $payload, string $clientIdentifier, string $applicationContext): ProvisioningOutcome
    {
        $this->runtime->assertEnabled();

        $coreIssuer = (string) ($payload['core_issuer'] ?? 'sicode-core');
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? 'idem-suspend-user');

        $lockKey = implode(':', [
            'core-provisioning-user-suspend',
            strtolower($applicationContext),
            $clientIdentifier,
            $coreSubject,
            $idempotencyKey,
        ]);

        return $this->lock->withLock($lockKey, function () use ($coreIssuer, $coreSubject, $applicationContext): ProvisioningOutcome {
            return DB::transaction(function () use ($coreIssuer, $coreSubject, $applicationContext): ProvisioningOutcome {
                $link = CoreIdentityLink::query()
                    ->where('core_issuer', $coreIssuer)
                    ->where('core_subject', $coreSubject)
                    ->where('application_context', $applicationContext)
                    ->lockForUpdate()
                    ->first();

                if (! $link instanceof CoreIdentityLink) {
                    throw new ProvisioningRejected('IDENTITY_LINK_REQUIRED');
                }

                if ($link->status === CoreIdentityLink::STATUS_SUSPENDED) {
                    return new ProvisioningOutcome(ProvisioningOutcome::RESULT_ALREADY_SUSPENDED, 'user', [
                        'core_subject' => $coreSubject,
                        'user_id' => $link->legacy_user_id,
                    ]);
                }

                $link->forceFill(['status' => CoreIdentityLink::STATUS_SUSPENDED])->save();

                return new ProvisioningOutcome(ProvisioningOutcome::RESULT_SUSPENDED, 'user', [
                    'core_subject' => $coreSubject,
                    'user_id' => $link->legacy_user_id,
                ]);
            });
        });
    }
}
