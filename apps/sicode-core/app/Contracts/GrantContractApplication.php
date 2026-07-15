<?php

declare(strict_types=1);

namespace App\Contracts;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Application;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\ContractApplicationGrant;
use App\Models\ContractApplicationGrantStatus;
use App\Models\ContractStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GrantContractApplication
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        Contract $contract,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $reason = null,
        ?string $correlationId = null,
    ): ContractApplicationGrant {
        if ($endsAt !== null && $endsAt->lt($startsAt)) {
            throw new InvalidArgumentException('Grant end date cannot be before its start date.');
        }

        return DB::transaction(function () use ($contract, $application, $context, $startsAt, $endsAt, $actorType, $actorId, $reason, $correlationId): ContractApplicationGrant {
            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedContract->status === ContractStatus::Ended->value) {
                throw new InvalidArgumentException('Ended contracts cannot receive application grants.');
            }

            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->status !== 'active') {
                throw new InvalidArgumentException('Only active applications can be granted to contracts.');
            }

            $lockedContext = null;
            if ($context instanceof ApplicationContext) {
                /** @var ApplicationContext $lockedContext */
                $lockedContext = ApplicationContext::query()
                    ->whereKey($context->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedContext->application_id !== $lockedApplication->id) {
                    throw new InvalidArgumentException('Grant context must belong to the granted application.');
                }

                if ($lockedContext->status !== 'active') {
                    throw new InvalidArgumentException('Only active application contexts can be granted to contracts.');
                }
            }

            if ($this->activeGrantExists($lockedContract, $lockedApplication, $lockedContext)) {
                throw new InvalidArgumentException('An active equivalent grant already exists for this contract.');
            }

            $grant = new ContractApplicationGrant([
                'status' => ContractApplicationGrantStatus::Active->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $grant->contract()->associate($lockedContract);
            $grant->application()->associate($lockedApplication);
            $grant->context()->associate($lockedContext);
            $grant->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::ContractApplicationGrantGranted,
                subjectType: CoreAuditSubjectType::ContractApplicationGrant,
                subjectId: $grant->id,
                applicationId: $lockedApplication->id,
                contextId: $lockedContext?->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'contract_id' => $lockedContract->id,
                    'application_id' => $lockedApplication->id,
                    'context_id' => $lockedContext?->id,
                ],
            ));

            return $grant;
        });
    }

    private function activeGrantExists(
        Contract $contract,
        Application $application,
        ?ApplicationContext $context,
    ): bool {
        return ContractApplicationGrant::query()
            ->where('contract_id', $contract->id)
            ->where('application_id', $application->id)
            ->when(
                $context instanceof ApplicationContext,
                fn ($query) => $query->where('context_id', $context->id),
                fn ($query) => $query->whereNull('context_id'),
            )
            ->where('status', ContractApplicationGrantStatus::Active->value)
            ->exists();
    }
}
