<?php

namespace App\CoreProvisioning;

final class ProvisioningOutcome
{
    public const RESULT_CREATED = 'created';
    public const RESULT_ALREADY_PROVISIONED = 'already_provisioned';
    public const RESULT_UPDATED = 'updated';
    public const RESULT_CONFLICT = 'conflict';
    public const RESULT_REJECTED = 'rejected';

    /**
     * @param array<string, mixed> $resource
     */
    public function __construct(
        public readonly string $result,
        public readonly string $resourceType,
        public readonly array $resource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result,
            $this->resourceType => $this->resource,
        ];
    }
}
