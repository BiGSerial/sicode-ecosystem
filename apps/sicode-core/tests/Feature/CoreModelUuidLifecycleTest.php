<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application as CoreApplication;
use App\Models\ApplicationAccess;
use App\Models\ApplicationContext;
use App\Models\ExternalIdentity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoreModelUuidLifecycleTest extends TestCase
{
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('CoreModel UUID lifecycle requires PostgreSQL UUID defaults.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        User::flushEventListeners();
        ExternalIdentity::flushEventListeners();
        ApplicationAccess::flushEventListeners();

        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_create_hydrates_postgresql_uuid_and_marks_model_as_persisted(): void
    {
        $user = $this->createUser('Create Lifecycle');

        $this->assertUuid($user->getKey());
        $this->assertSame($user->id, $user->getKey());
        $this->assertTrue($user->exists);
        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
        $this->assertSame([], $user->getDirty());
        $this->assertSame($user->id, $user->getOriginal('id'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_save_hydrates_postgresql_uuid_on_new_model(): void
    {
        $user = $this->newUser('Save Lifecycle');

        $this->assertNull($user->getKey());

        $this->assertTrue($user->save());

        $this->assertUuid($user->getKey());
        $this->assertTrue($user->exists);
        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertSame([], $user->getDirty());
        $this->assertSame($user->id, $user->getOriginal('id'));
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_model_events_see_uuid_only_after_database_insert_returns(): void
    {
        $events = [];

        User::saving(function (User $user) use (&$events): void {
            $events[] = $this->eventSnapshot('saving', $user);
        });

        User::creating(function (User $user) use (&$events): void {
            $events[] = $this->eventSnapshot('creating', $user);
        });

        User::created(function (User $user) use (&$events): void {
            $events[] = $this->eventSnapshot('created', $user);
        });

        User::saved(function (User $user) use (&$events): void {
            $events[] = $this->eventSnapshot('saved', $user);
        });

        $user = $this->createUser('Event Lifecycle');

        $this->assertSame(['saving', 'creating', 'created', 'saved'], array_column($events, 'name'));
        $this->assertNull($events[0]['key']);
        $this->assertNull($events[1]['key']);
        $this->assertSame($user->id, $events[2]['key']);
        $this->assertSame($user->id, $events[3]['key']);
        $this->assertFalse($events[0]['exists']);
        $this->assertFalse($events[1]['exists']);
        $this->assertTrue($events[2]['exists']);
        $this->assertTrue($events[3]['exists']);
        $this->assertFalse($events[0]['was_recently_created']);
        $this->assertFalse($events[1]['was_recently_created']);
        $this->assertTrue($events[2]['was_recently_created']);
        $this->assertTrue($events[3]['was_recently_created']);
        $this->assertContains('id', $events[2]['dirty']);
        $this->assertContains('id', $events[3]['dirty']);
        $this->assertNull($events[2]['original_id']);
        $this->assertNull($events[3]['original_id']);
        $this->assertSame([], $user->getDirty());
        $this->assertSame($user->id, $user->getOriginal('id'));
    }

    public function test_refresh_update_and_find_use_hydrated_uuid_key(): void
    {
        $user = $this->createUser('Find Lifecycle');
        $id = $user->id;

        $this->assertTrue($user->update(['display_name' => 'Updated Lifecycle']));
        $this->assertSame($id, $user->id);

        $user->refresh();

        $this->assertSame('Updated Lifecycle', $user->display_name);
        $this->assertSame($id, $user->getKey());

        $found = User::find($id);

        $this->assertInstanceOf(User::class, $found);
        $this->assertSame($id, $found->id);
    }

    public function test_relationship_create_hydrates_related_model_uuid(): void
    {
        $user = $this->createUser('Relation Lifecycle');

        $externalIdentity = $user->externalIdentities()->create([
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => 'relation-lifecycle',
            'status' => 'active',
            'linked_at' => now(),
        ]);

        $this->assertUuid($externalIdentity->getKey());
        $this->assertSame($user->id, $externalIdentity->user_id);
        $this->assertTrue($externalIdentity->exists);
        $this->assertTrue($externalIdentity->wasRecentlyCreated);
        $this->assertSame([], $externalIdentity->getDirty());
        $this->assertDatabaseHas('external_identities', ['id' => $externalIdentity->id]);
    }

    public function test_create_quietly_and_save_quietly_hydrate_uuid_without_events(): void
    {
        $events = [];

        User::saving(function () use (&$events): void {
            $events[] = 'saving';
        });

        User::creating(function () use (&$events): void {
            $events[] = 'creating';
        });

        User::created(function () use (&$events): void {
            $events[] = 'created';
        });

        User::saved(function () use (&$events): void {
            $events[] = 'saved';
        });

        $createdQuietly = User::createQuietly($this->newUserAttributes('Create Quietly'));
        $savedQuietly = $this->newUser('Save Quietly');

        $this->assertTrue($savedQuietly->saveQuietly());
        $this->assertSame([], $events);
        $this->assertUuid($createdQuietly->getKey());
        $this->assertUuid($savedQuietly->getKey());
        $this->assertTrue($createdQuietly->exists);
        $this->assertTrue($savedQuietly->exists);
    }

    public function test_uuid_lifecycle_regression_for_user_external_identity_and_application_access(): void
    {
        $user = $this->createUser('Regression Lifecycle');
        $application = $this->createCoreApplication('regression-lifecycle');
        $context = $this->createContext($application, 'es');

        $externalIdentity = $user->externalIdentities()->create([
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => 'regression-lifecycle',
            'status' => 'active',
            'linked_at' => now(),
        ]);

        $access = new ApplicationAccess([
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);
        $access->user()->associate($user);
        $access->application()->associate($application);
        $access->context()->associate($context);

        $this->assertTrue($access->save());

        foreach ([$user, $externalIdentity, $access] as $model) {
            $this->assertUuid($model->getKey());
            $this->assertTrue($model->exists);
            $this->assertTrue($model->wasRecentlyCreated);
            $this->assertSame([], $model->getDirty());
            $this->assertSame($model->getKey(), $model->getOriginal('id'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSnapshot(string $name, User $user): array
    {
        return [
            'name' => $name,
            'key' => $user->getKey(),
            'exists' => $user->exists,
            'was_recently_created' => $user->wasRecentlyCreated,
            'dirty' => array_keys($user->getDirty()),
            'original_id' => $user->getOriginal('id'),
        ];
    }

    private function createUser(string $displayName): User
    {
        return User::create($this->newUserAttributes($displayName));
    }

    private function newUser(string $displayName): User
    {
        return new User($this->newUserAttributes($displayName));
    }

    /**
     * @return array<string, string>
     */
    private function newUserAttributes(string $displayName): array
    {
        $this->sequence++;

        $email = 'lifecycle-'.$this->sequence.'@example.test';

        return [
            'display_name' => $displayName,
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => 'active',
        ];
    }

    private function createCoreApplication(string $code): CoreApplication
    {
        return CoreApplication::create([
            'code' => $code,
            'name' => $code,
            'status' => 'active',
            'requires_organization' => false,
            'requires_contract' => false,
        ]);
    }

    private function createContext(CoreApplication $application, string $code): ApplicationContext
    {
        return $application->contexts()->create([
            'code' => $code,
            'name' => $code,
            'status' => 'active',
        ]);
    }

    private function assertUuid(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $value,
        );
    }
}
