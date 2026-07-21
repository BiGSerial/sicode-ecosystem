<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApplicationAccesses\GrantApplicationAccess;
use App\ApplicationLaunch\IssueApplicationLaunch;
use App\Contracts\GrantContractApplication;
use App\CoreAudit\CoreAuditActorType;
use App\LegacyProvisioning\ProvisionLegacySpAccess;
use App\Models\Application;
use App\Models\ApplicationClient;
use App\Models\ApplicationContext;
use App\Models\ApplicationLaunch;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class RunLegacySpE2eLifecycle
{
    private const PREFIX = 'TEST_E2E_SP_';

    public function __invoke(Command $command): int
    {
        try {
            $runId = $this->runId($command);
            $cleanupOnly = (bool) $command->option('cleanup-only');
            $this->assertAllowed($cleanupOnly);

            if ($cleanupOnly) {
                $this->cleanup($runId);
                $command->line(json_encode(['status' => 'cleaned', 'run_id' => $runId], JSON_THROW_ON_ERROR));

                return 0;
            }

            $baseline = $this->coreCounts();
            $fixtures = $this->createFixtures($runId);

            $provisioning = app(ProvisionLegacySpAccess::class)($fixtures['user'], $fixtures['organization']);
            if ($provisioning->overall !== 'provisioned' || ! $provisioning->organization->isSuccessful() || $provisioning->user?->isSuccessful() !== true) {
                throw new RuntimeException('Legacy SP provisioning failed before launch.');
            }

            $idempotent = app(ProvisionLegacySpAccess::class)($fixtures['user'], $fixtures['organization']);
            if (! $idempotent->organization->isSuccessful() || $idempotent->user?->isSuccessful() !== true) {
                throw new RuntimeException('Legacy SP idempotent provisioning retry failed.');
            }

            $partial = $this->exercisePartialFailure($runId, $fixtures['organization']);
            $launch = $this->issueLaunch($fixtures);
            $session = $this->consumeLaunchOverHttp($launch['callback_url'], $launch['code'], $launch['state']);

            $this->assertSession($session, $fixtures);

            $secretNeedles = [
                (string) config('legacy_provisioning.sp.client_secret'),
                (string) config('core_launch.client_secrets.'.$fixtures['client']->client_identifier),
                $launch['code'],
            ];
            $this->assertNoNeedleInCorePersistence($secretNeedles);

            $result = [
                'status' => 'passed',
                'run_id' => $runId,
                'baseline_before' => $baseline,
                'core_counts_after_flow' => $this->coreCounts(),
                'core' => [
                    'user_id' => $fixtures['user']->id,
                    'organization_id' => $fixtures['organization']->id,
                    'application' => $fixtures['application']->code,
                    'context' => $fixtures['context']->code,
                    'client_identifier' => $fixtures['client']->client_identifier,
                    'launch_id' => $launch['launch_id'],
                    'provisioning' => [
                        'organization' => $provisioning->organization->outcome->value,
                        'user' => $provisioning->user->outcome->value,
                        'overall' => $provisioning->overall,
                    ],
                    'idempotent_retry' => [
                        'organization' => $idempotent->organization->outcome->value,
                        'user' => $idempotent->user->outcome->value,
                    ],
                    'partial_failure' => $partial,
                ],
                'legacy_session' => $session,
                'cleanup_note' => 'Run core:e2e:legacy-sp-lifecycle --run-id='.$runId.' --cleanup-only after Legacy cleanup verification.',
            ];

            $command->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return 0;
        } catch (\Throwable $throwable) {
            $command->error($throwable->getMessage());

            return 1;
        }
    }

    private function assertAllowed(bool $cleanupOnly = false): void
    {
        if (! App::environment('testing')) {
            throw new RuntimeException('E2E requires APP_ENV=testing.');
        }

        if (! $this->runtimeBoolean('SICODE_E2E_ALLOWED')) {
            throw new RuntimeException('E2E requires SICODE_E2E_ALLOWED=true.');
        }

        if (! $this->runtimeBoolean('LEGACY_TEST_DATABASE_ALLOWED')) {
            throw new RuntimeException('E2E requires LEGACY_TEST_DATABASE_ALLOWED=true.');
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');
        $host = (string) config('database.connections.'.config('database.default').'.host');
        if ($database !== 'sicode_core' || ! in_array($host, ['sicode-postgres', '127.0.0.1', 'localhost'], true)) {
            throw new RuntimeException('E2E refused the configured CORE database.');
        }

        if (! $cleanupOnly) {
            $baseUrl = (string) config('legacy_provisioning.sp.base_url');
            $host = (string) parse_url($baseUrl, PHP_URL_HOST);
            if (! (bool) config('legacy_provisioning.sp.enabled') || (string) config('legacy_provisioning.sp.expected_context') !== 'sp') {
                throw new RuntimeException('E2E requires enabled Legacy SP provisioning with context sp.');
            }

            if (! in_array($host, ['sicode-legacy', '127.0.0.1', 'localhost'], true)) {
                throw new RuntimeException('E2E refused the configured Legacy host.');
            }
        }
    }

    private function runId(Command $command): string
    {
        $option = $command->option('run-id');
        if (is_string($option) && preg_match('/^[A-Za-z0-9_-]{8,80}$/', $option) === 1) {
            return $option;
        }

        return strtolower((string) Str::uuid());
    }

    /**
     * @return array<string, int>
     */
    private function coreCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'organizations' => Organization::query()->count(),
            'organization_memberships' => OrganizationMembership::query()->count(),
            'application_launches' => ApplicationLaunch::query()->count(),
            'legacy_provisioning_operations' => DB::table('legacy_provisioning_operations')->count(),
            'core_audit_events' => DB::table('core_audit_events')->count(),
        ];
    }

    /**
     * @return array{user: User, organization: Organization, application: Application, context: ApplicationContext, client: ApplicationClient, contract: Contract}
     */
    private function createFixtures(string $runId): array
    {
        $prefix = self::PREFIX.$runId;
        $now = CarbonImmutable::now();

        $application = Application::query()->firstOrCreate(
            ['code' => 'sicode-legacy'],
            [
                'name' => 'SICODE Legacy',
                'status' => 'active',
                'requires_organization' => true,
                'requires_contract' => true,
            ],
        );
        $application->forceFill([
            'status' => 'active',
            'requires_organization' => true,
            'requires_contract' => true,
        ])->save();

        $context = $application->contexts()->firstOrCreate(
            ['code' => 'sp'],
            [
                'name' => 'SP',
                'status' => 'active',
                'requires_organization' => true,
                'requires_contract' => true,
            ],
        );
        $context->forceFill([
            'status' => 'active',
            'requires_organization' => true,
            'requires_contract' => true,
        ])->save();

        $client = ApplicationClient::query()->where('client_identifier', 'sicode-legacy-sp-e2e')->first();
        if (! $client instanceof ApplicationClient) {
            $client = new ApplicationClient([
                'client_identifier' => 'sicode-legacy-sp-e2e',
                'name' => 'SICODE Legacy SP E2E',
                'type' => 'web',
                'status' => 'active',
            ]);
            $client->application()->associate($application);
            $client->context()->associate($context);
            $client->save();
        }

        $client->forceFill(['status' => 'active'])->save();
        $legacyPort = $this->runtimeInteger('SICODE_E2E_LEGACY_PORT', 8001);
        $quotedCallback = DB::getPdo()->quote('https://sicode-legacy:'.$legacyPort.'/core/launch/callback');
        DB::table('application_clients')
            ->where('id', $client->id)
            ->update(['application_id' => $application->id, 'context_id' => $context->id, 'redirect_uris' => DB::raw('ARRAY['.$quotedCallback.']::text[]')]);
        $client->refresh();

        $user = User::create([
            'display_name' => $prefix.'_User',
            'primary_email' => strtolower($prefix).'@example.test',
            'primary_email_normalized' => strtolower($prefix).'@example.test',
            'status' => 'active',
        ]);

        $organization = Organization::create([
            'name' => $prefix.'_Organization',
            'legal_name' => $prefix.'_Organization Ltda',
            'document_type' => null,
            'document_value' => null,
            'status' => 'active',
        ]);

        $membership = new OrganizationMembership([
            'status' => 'active',
            'started_at' => $now->subDay(),
            'ended_at' => null,
        ]);
        $membership->user()->associate($user);
        $membership->organization()->associate($organization);
        $membership->save();

        $contract = new Contract([
            'identifier' => $prefix.'_Contract',
            'status' => 'active',
            'starts_at' => $now->subDay(),
            'ends_at' => null,
        ]);
        $contract->organization()->associate($organization);
        $contract->save();

        app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: $context,
            startsAt: $now->subDay(),
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'TEST_E2E_SP access fixture',
        );

        app(GrantContractApplication::class)(
            contract: $contract,
            application: $application,
            context: $context,
            startsAt: $now->subDay(),
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'TEST_E2E_SP grant fixture',
        );

        return compact('user', 'organization', 'application', 'context', 'client', 'contract');
    }

    /**
     * @param  array{user: User, organization: Organization, application: Application, context: ApplicationContext, client: ApplicationClient, contract: Contract}  $fixtures
     * @return array{launch_id: string, callback_url: string, code: string, state: string}
     */
    private function issueLaunch(array $fixtures): array
    {
        $redirect = app(IssueApplicationLaunch::class)(
            user: $fixtures['user'],
            application: $fixtures['application'],
            context: $fixtures['context'],
            at: CarbonImmutable::now(),
        );

        return [
            'launch_id' => $redirect->launchId,
            'callback_url' => $redirect->callbackUrl,
            'code' => $redirect->code,
            'state' => $redirect->state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consumeLaunchOverHttp(string $callbackUrl, string $code, string $state): array
    {
        $legacyPort = $this->runtimeInteger('SICODE_E2E_LEGACY_PORT', 8001);
        $callback = str_replace('https://sicode-legacy:'.$legacyPort, 'http://sicode-legacy:'.$legacyPort, $callbackUrl);
        $jar = new CookieJar;

        $response = Http::withOptions(['cookies' => $jar, 'allow_redirects' => false])
            ->get($callback, ['code' => $code, 'state' => $state]);

        if ($response->status() !== 302) {
            throw new RuntimeException('Legacy callback did not establish a redirecting session.');
        }

        $location = (string) $response->header('Location');
        if (! str_ends_with($location, '/home')) {
            throw new RuntimeException('Legacy callback did not redirect to the authenticated home route; location='.$location.'.');
        }

        $cookieHeader = $this->cookieHeader($response->headers()['Set-Cookie'] ?? []);

        $protected = Http::withOptions(['cookies' => $jar])
            ->withHeaders($cookieHeader !== '' ? ['Cookie' => $cookieHeader] : [])
            ->acceptJson()
            ->get('http://sicode-legacy:'.$legacyPort.'/__testing/core-e2e/current-company');

        if (! $protected->ok()) {
            throw new RuntimeException('Legacy protected current-company route was not accessible; status='.$protected->status().'.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $protected->json();

        return $payload;
    }

    /**
     * @param  list<string>  $setCookies
     */
    private function cookieHeader(array $setCookies): string
    {
        $pairs = [];

        foreach ($setCookies as $setCookie) {
            $pair = strtok($setCookie, ';');
            if (is_string($pair)) {
                $pairs[] = $pair;
            }
        }

        return implode('; ', $pairs);
    }

    private function runtimeBoolean(string $key): bool
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function runtimeInteger(string $key, int $default): int
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array{user: User, organization: Organization, application: Application, context: ApplicationContext, client: ApplicationClient, contract: Contract}  $fixtures
     * @param  array<string, mixed>  $session
     */
    private function assertSession(array $session, array $fixtures): void
    {
        if (($session['authenticated'] ?? null) !== true) {
            throw new RuntimeException('Legacy session was not authenticated.');
        }

        if (($session['source'] ?? null) !== 'core') {
            throw new RuntimeException('Legacy CurrentCompanyContext source is not core.');
        }

        if (strtoupper((string) ($session['application_context'] ?? '')) !== 'SP') {
            throw new RuntimeException('Legacy CurrentCompanyContext context is not SP.');
        }

        if (($session['core_organization_id'] ?? null) !== $fixtures['organization']->id) {
            throw new RuntimeException('Legacy CurrentCompanyContext has the wrong CORE organization.');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function exercisePartialFailure(string $runId, Organization $organization): array
    {
        $user = User::create([
            'display_name' => self::PREFIX.$runId.'_NoMembership',
            'primary_email' => strtolower(self::PREFIX.$runId).'_nomembership@example.test',
            'primary_email_normalized' => strtolower(self::PREFIX.$runId).'_nomembership@example.test',
            'status' => 'active',
        ]);

        $result = app(ProvisionLegacySpAccess::class)($user, $organization);

        return [
            'overall' => $result->overall,
            'organization' => $result->organization->outcome->value,
            'user' => $result->user?->outcome->value,
            'user_error_category' => $result->user?->errorCategory?->value,
        ];
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertNoNeedleInCorePersistence(array $needles): void
    {
        $payload = DB::table('legacy_provisioning_operations')->get()->toJson()
            .DB::table('core_audit_events')->latest('occurred_at')->limit(100)->get()->toJson();

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains((string) $payload, $needle)) {
                throw new RuntimeException('Sensitive E2E value appeared in CORE persistence.');
            }
        }
    }

    private function cleanup(string $runId): void
    {
        $prefix = self::PREFIX.$runId;
        $users = User::query()->where('display_name', 'like', $prefix.'%')->pluck('id');
        $organizations = Organization::query()->where('name', 'like', $prefix.'%')->pluck('id');

        DB::transaction(function () use ($users, $organizations): void {
            DB::table('application_launches')->whereIn('user_id', $users)->delete();
            DB::table('legacy_provisioning_operations')
                ->where(function ($query) use ($users, $organizations): void {
                    $query->whereIn('entity_id', $users)
                        ->orWhereIn('entity_id', $organizations)
                        ->orWhereIn('organization_id', $organizations);
                })
                ->delete();
            DB::table('application_accesses')->whereIn('user_id', $users)->delete();
            DB::table('contract_application_grants')
                ->whereIn('contract_id', DB::table('contracts')->whereIn('organization_id', $organizations)->pluck('id'))
                ->delete();
            DB::table('contracts')->whereIn('organization_id', $organizations)->delete();
            DB::table('organization_memberships')->whereIn('user_id', $users)->orWhereIn('organization_id', $organizations)->delete();
            DB::table('users')->whereIn('id', $users)->delete();
            DB::table('organizations')->whereIn('id', $organizations)->delete();
        });
    }
}
