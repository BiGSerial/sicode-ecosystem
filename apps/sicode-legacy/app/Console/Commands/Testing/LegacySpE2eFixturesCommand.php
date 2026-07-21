<?php

namespace App\Console\Commands\Testing;

use App\Testing\LegacyDumpDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{App, Config, DB, Schema};
use RuntimeException;

class LegacySpE2eFixturesCommand extends Command
{
    protected $signature = 'legacy:e2e:sp-fixtures {action : inspect|cleanup|verify-clean} {run_id}';

    protected $description = 'Inspect or clean isolated CORE -> Legacy SP E2E fixtures.';

    public function handle(): int
    {
        try {
            $this->assertAllowed();

            $action = (string) $this->argument('action');
            $runId  = (string) $this->argument('run_id');

            if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $runId)) {
                throw new RuntimeException('Invalid E2E run id.');
            }

            $payload = match ($action) {
                'inspect'      => $this->inspect($runId),
                'cleanup'      => $this->cleanup($runId),
                'verify-clean' => $this->verifyClean($runId),
                default        => throw new RuntimeException('Unsupported E2E fixture action.'),
            };

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return 0;
        } catch (\Throwable $throwable) {
            $this->error($throwable->getMessage());

            return 1;
        }
    }

    private function assertAllowed(): void
    {
        app(LegacyDumpDatabaseGuard::class)->assertAllowed();

        if (!App::environment('testing')) {
            throw new RuntimeException('Legacy SP E2E requires APP_ENV=testing.');
        }

        if (!$this->runtimeBoolean('SICODE_E2E_ALLOWED')) {
            throw new RuntimeException('Legacy SP E2E requires SICODE_E2E_ALLOWED=true.');
        }

        if ((string) Config::get('sicode.unit') !== 'sp') {
            throw new RuntimeException('Legacy SP E2E requires SICODE_UNIT=sp.');
        }

        if ((string) Config::get('sicode.identity_mode') !== 'provisioning') {
            throw new RuntimeException('Legacy SP E2E requires SICODE_IDENTITY_MODE=provisioning.');
        }

        if ((string) Config::get('sicode.core.expected_context') !== 'SP') {
            throw new RuntimeException('Legacy SP E2E requires CORE_APPLICATION_CONTEXT=sp / CORE_LAUNCH_CONTEXT=SP.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(string $runId): array
    {
        return [
            'status'          => 'inspected',
            'run_id'          => $runId,
            'baseline_counts' => $this->counts(),
            'fixtures'        => $this->fixtureCounts($runId),
            'links'           => DB::table('core_identity_links')
                ->join('users', 'users.id', '=', 'core_identity_links.legacy_user_id')
                ->where('users.email', 'like', strtolower($this->prefix($runId)) . '%')
                ->select('core_identity_links.core_subject', 'core_identity_links.legacy_user_id', 'users.company_id', 'core_identity_links.application_context')
                ->get(),
            'organization_links' => DB::table('core_organization_links')
                ->join('companies', 'companies.id', '=', 'core_organization_links.company_id')
                ->where('companies.name', 'like', $this->prefix($runId) . '%')
                ->select('core_organization_links.core_organization_id', 'core_organization_links.company_id', 'core_organization_links.application_context')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cleanup(string $runId): array
    {
        $prefix     = $this->prefix($runId);
        $userIds    = DB::table('users')->where('email', 'like', strtolower($prefix) . '%')->pluck('id');
        $companyIds = DB::table('companies')->where('name', 'like', $prefix . '%')->pluck('id');

        DB::transaction(function () use ($userIds, $companyIds): void {
            DB::table('core_identity_links')->whereIn('legacy_user_id', $userIds)->delete();
            DB::table('core_organization_links')->whereIn('company_id', $companyIds)->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('payload', 'like', '%core_launch%')->delete();
            }

            DB::table('users')->whereIn('id', $userIds)->delete();
            DB::table('companies')->whereIn('id', $companyIds)->delete();
        });

        return [
            'status'    => 'cleaned',
            'run_id'    => $runId,
            'remaining' => $this->fixtureCounts($runId),
            'counts'    => $this->counts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyClean(string $runId): array
    {
        $remaining = $this->fixtureCounts($runId);
        $dirty     = array_filter($remaining, fn (int $count): bool => $count !== 0);

        if ($dirty !== []) {
            throw new RuntimeException('Legacy SP E2E fixtures remain after cleanup.');
        }

        return [
            'status' => 'clean',
            'run_id' => $runId,
            'counts' => $this->counts(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function fixtureCounts(string $runId): array
    {
        $prefix = $this->prefix($runId);

        return [
            'companies'               => DB::table('companies')->where('name', 'like', $prefix . '%')->count(),
            'users'                   => DB::table('users')->where('email', 'like', strtolower($prefix) . '%')->count(),
            'core_organization_links' => DB::table('core_organization_links')
                ->join('companies', 'companies.id', '=', 'core_organization_links.company_id')
                ->where('companies.name', 'like', $prefix . '%')
                ->count(),
            'core_identity_links' => DB::table('core_identity_links')
                ->join('users', 'users.id', '=', 'core_identity_links.legacy_user_id')
                ->where('users.email', 'like', strtolower($prefix) . '%')
                ->count(),
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function counts(): array
    {
        return [
            'companies'               => DB::table('companies')->count(),
            'users'                   => DB::table('users')->count(),
            'core_identity_links'     => DB::table('core_identity_links')->count(),
            'core_organization_links' => DB::table('core_organization_links')->count(),
            'productions'             => Schema::hasTable('productions') ? DB::table('productions')->count() : null,
            'work_reports'            => Schema::hasTable('work_reports') ? DB::table('work_reports')->count() : null,
            'sessions'                => Schema::hasTable('sessions') ? DB::table('sessions')->count() : null,
        ];
    }

    private function prefix(string $runId): string
    {
        return 'TEST_E2E_SP_' . $runId;
    }

    private function runtimeBoolean(string $key): bool
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
