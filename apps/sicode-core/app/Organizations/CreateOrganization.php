<?php

declare(strict_types=1);

namespace App\Organizations;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationStatus;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\DB;

final class CreateOrganization
{
    public function __construct(
        private readonly ValidatorFactory $validator,
        private readonly OrganizationDocumentNormalizer $documentNormalizer,
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        string $name,
        ?string $legalName,
        ?string $documentType,
        ?string $documentValue,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $reason = null,
        ?string $correlationId = null,
    ): Organization {
        $document = $this->documentNormalizer->normalize($documentType, $documentValue);

        $validated = $this->validator->make(
            [
                'name' => trim($name),
                'legal_name' => $legalName === null ? null : trim($legalName),
                'document_type' => $document['type'],
                'document_value' => $document['value'],
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'legal_name' => ['nullable', 'string', 'max:255'],
                'document_type' => ['nullable', 'string', 'max:80', 'required_with:document_value'],
                'document_value' => ['nullable', 'string', 'max:120', 'required_with:document_type'],
            ],
        )->validate();

        return DB::transaction(function () use ($validated, $actorType, $actorId, $reason, $correlationId): Organization {
            $organization = Organization::create([
                'name' => $validated['name'],
                'legal_name' => $validated['legal_name'] ?? null,
                'document_type' => $validated['document_type'] ?? null,
                'document_value' => $validated['document_value'] ?? null,
                'status' => OrganizationStatus::Active->value,
            ]);

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::OrganizationCreated,
                subjectType: CoreAuditSubjectType::Organization,
                subjectId: $organization->id,
                reason: $reason,
                correlationId: $correlationId,
            ));

            return $organization;
        });
    }
}
