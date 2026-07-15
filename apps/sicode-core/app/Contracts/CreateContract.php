<?php

declare(strict_types=1);

namespace App\Contracts;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Organization;
use App\Models\OrganizationStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateContract
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        Organization $organization,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        ?string $identifier,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $reason = null,
        ?string $correlationId = null,
    ): Contract {
        $identifier = $identifier === null ? null : trim($identifier);

        if ($identifier === '') {
            $identifier = null;
        }

        if ($endsAt !== null && $endsAt->lt($startsAt)) {
            throw new InvalidArgumentException('Contract end date cannot be before its start date.');
        }

        return DB::transaction(function () use ($organization, $startsAt, $endsAt, $identifier, $actorType, $actorId, $reason, $correlationId): Contract {
            /** @var Organization $lockedOrganization */
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrganization->status !== OrganizationStatus::Active->value) {
                throw new InvalidArgumentException('Only active organizations can receive new contracts.');
            }

            $contract = new Contract([
                'identifier' => $identifier,
                'status' => ContractStatus::Draft->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $contract->organization()->associate($lockedOrganization);
            $contract->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::ContractCreated,
                subjectType: CoreAuditSubjectType::Contract,
                subjectId: $contract->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'organization_id' => $lockedOrganization->id,
                    'status' => ContractStatus::Draft->value,
                ],
            ));

            return $contract;
        });
    }
}
