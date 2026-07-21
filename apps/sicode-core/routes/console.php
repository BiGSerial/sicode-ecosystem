<?php

use App\LegacyProvisioning\LegacyProvisioningConfiguration;
use App\LegacyProvisioning\ProvisionLegacySpAccess;
use App\LegacyProvisioning\ProvisionOrganizationToLegacySp;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('core:legacy-sp:provision-organization {organization_id} {--dry-run}', function (): int {
    /** @var string $organizationId */
    $organizationId = $this->argument('organization_id');
    $organization = Organization::query()->find($organizationId);

    if (! $organization instanceof Organization) {
        $this->error('Organization not found.');

        return 1;
    }

    if ((bool) $this->option('dry-run')) {
        return legacySpProvisioningDryRunOrganization($this, $organization);
    }

    try {
        $result = app(ProvisionOrganizationToLegacySp::class)($organization);
    } catch (InvalidArgumentException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->line('outcome='.$result->outcome->value);
    $this->line('attempts='.$result->attempts);

    if ($result->remoteLocalId !== null) {
        $this->line('remote_local_id='.$result->remoteLocalId);
    }

    return $result->outcome->isSuccessful() ? 0 : 1;
})->purpose('Provision a CORE organization projection into Legacy SP.');

Artisan::command('core:legacy-sp:provision-user {user_id} {organization_id} {--dry-run}', function (): int {
    /** @var string $userId */
    $userId = $this->argument('user_id');
    /** @var string $organizationId */
    $organizationId = $this->argument('organization_id');

    $user = User::query()->find($userId);
    $organization = Organization::query()->find($organizationId);

    if (! $user instanceof User || ! $organization instanceof Organization) {
        $this->error('User or organization not found.');

        return 1;
    }

    if ((bool) $this->option('dry-run')) {
        return legacySpProvisioningDryRunUser($this, $user, $organization);
    }

    try {
        $result = app(ProvisionLegacySpAccess::class)($user, $organization);
    } catch (InvalidArgumentException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->line('organization='.$result->organization->outcome->value);
    $this->line('user='.($result->user?->outcome->value ?? 'not_attempted'));
    $this->line('overall='.$result->overall);

    return $result->user?->outcome->isSuccessful() === true ? 0 : 1;
})->purpose('Provision a CORE user projection into Legacy SP after organization provisioning.');

if (! function_exists('legacySpProvisioningDryRunOrganization')) {
    function legacySpProvisioningDryRunOrganization(Command $command, Organization $organization): int
    {
        try {
            LegacyProvisioningConfiguration::sp()->assertUsable();
        } catch (InvalidArgumentException $exception) {
            $command->error($exception->getMessage());

            return 1;
        }

        if ($organization->status !== OrganizationStatus::Active->value) {
            $command->error('Organization is not active.');

            return 1;
        }

        $command->line('dry_run=ok');
        $command->line('organization='.$organization->getKey());
        $command->line('target_context=sp');

        return 0;
    }
}

if (! function_exists('legacySpProvisioningDryRunUser')) {
    function legacySpProvisioningDryRunUser(Command $command, User $user, Organization $organization): int
    {
        $organizationDryRun = legacySpProvisioningDryRunOrganization($command, $organization);
        if ($organizationDryRun !== 0) {
            return $organizationDryRun;
        }

        if ($user->status !== 'active') {
            $command->error('User is not active.');

            return 1;
        }

        $now = CarbonImmutable::now();
        $hasMembership = OrganizationMembership::query()
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organization->getKey())
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->where('started_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now);
            })
            ->exists();

        if (! $hasMembership) {
            $command->error('User does not have an active membership in the organization.');

            return 1;
        }

        $command->line('user='.$user->getKey());
        $command->line('membership=active');

        return 0;
    }
}
