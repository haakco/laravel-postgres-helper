<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Integration;

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use HaakCo\PostgresHelper\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @internal
 *
 * @coversNothing
 */
final class EventTriggerTest extends TestCase
{
    use DatabaseTransactions;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have the helper functions
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
        PgHelperLibrary::addAutoApplyStandards();

        // Ensure event triggers are disabled at start
        if (PgHelperLibrary::areEventTriggersEnabled()) {
            PgHelperLibrary::enableEventTriggers(false);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        // Ensure event triggers are disabled after test
        if (PgHelperLibrary::areEventTriggersEnabled()) {
            PgHelperLibrary::enableEventTriggers(false);
        }

        parent::tearDown();
    }

    public function test_event_triggers_can_be_enabled_and_disabled(): void
    {
        // Initially should be disabled
        self::assertFalse(PgHelperLibrary::areEventTriggersEnabled());

        // Enable event triggers
        $result = PgHelperLibrary::enableEventTriggers(true);
        self::assertTrue($result['enabled']);
        self::assertStringContainsString('enabled', $result['message']);
        self::assertTrue(PgHelperLibrary::areEventTriggersEnabled());

        // Disable event triggers
        $result = PgHelperLibrary::enableEventTriggers(false);
        self::assertFalse($result['enabled']);
        self::assertStringContainsString('disabled', $result['message']);
        self::assertFalse(PgHelperLibrary::areEventTriggersEnabled());
    }

    public function test_event_trigger_automatically_applies_standards_to_new_tables(): void
    {
        // Enable event triggers
        PgHelperLibrary::enableEventTriggers(true);

        // Create a new table with updated_at column
        Schema::create('test_auto_standards', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Allow a moment for the trigger to execute
        sleep(1);

        // Check if trigger was created
        $hasTrigger = DB::selectOne('
            SELECT 1 FROM information_schema.triggers
            WHERE trigger_name = ?
            AND event_object_table = ?
        ', ['update_test_auto_standards_updated_at', 'test_auto_standards']);

        self::assertNotNull($hasTrigger, 'Trigger should have been automatically created');

        // Insert with explicit ID to test sequence fixing
        DB::table('test_auto_standards')->insert([
            'id' => 1000,
            'name' => 'Test',
        ]);

        // Check if sequence was fixed (next value should be > 1000)
        $nextId = DB::selectOne("SELECT nextval('test_auto_standards_id_seq') as next_id")->next_id;
        self::assertGreaterThan(1000, $nextId);

        // Clean up
        Schema::dropIfExists('test_auto_standards');
    }

    public function test_event_trigger_ignores_tables_without_updated_at(): void
    {
        // Enable event triggers
        PgHelperLibrary::enableEventTriggers(true);

        // Create a table without updated_at
        Schema::create('test_no_updated_at', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamp('created_at');
            // No updated_at
        });

        // Allow a moment for the trigger to execute
        sleep(1);

        // Check that no trigger was created
        $hasTrigger = DB::selectOne('
            SELECT 1 FROM information_schema.triggers
            WHERE trigger_name = ?
            AND event_object_table = ?
        ', ['update_test_no_updated_at_updated_at', 'test_no_updated_at']);

        self::assertNull($hasTrigger, 'No trigger should be created for tables without updated_at');

        // Clean up
        Schema::dropIfExists('test_no_updated_at');
    }

    public function test_apply_best_practices_with_dry_run(): void
    {
        // Create test tables
        Schema::create('test_dry_run_1', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('test_dry_run_2', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        // Insert data with explicit IDs
        DB::table('test_dry_run_1')->insert(['id' => 100]);
        DB::table('test_dry_run_2')->insert(['id' => 200]);

        // Run dry run
        $result = PgHelperLibrary::applyBestPractices(['test_dry_run_1', 'test_dry_run_2'], true);

        // Assert dry run results
        self::assertTrue($result['dry_run']);
        self::assertSame(2, $result['tables_processed']);

        // Should indicate what would be done
        $sequencesToFix = array_filter($result['sequences_fixed'], static fn ($seq): bool => str_contains($seq, 'would fix'));
        $triggersToCreate = array_filter($result['triggers_created'], static fn ($trig): bool => str_contains($trig, 'would create'));

        self::assertNotEmpty($sequencesToFix);
        self::assertNotEmpty($triggersToCreate);

        // Verify nothing was actually changed
        $trigger1 = DB::selectOne('
            SELECT 1 FROM information_schema.triggers
            WHERE trigger_name = ?
        ', ['update_test_dry_run_1_updated_at']);
        self::assertNull($trigger1);

        // Clean up
        Schema::dropIfExists('test_dry_run_2');
        Schema::dropIfExists('test_dry_run_1');
    }

    public function test_apply_best_practices_actual_run(): void
    {
        // Create test tables
        Schema::create('test_apply_1', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('test_apply_2', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        // Insert data with explicit IDs
        DB::table('test_apply_1')->insert(['id' => 100]);
        DB::table('test_apply_2')->insert(['id' => 200, 'title' => 'Test']);

        // Apply best practices
        $result = PgHelperLibrary::applyBestPractices(['test_apply_1', 'test_apply_2'], false);

        // Assert results
        self::assertFalse($result['dry_run']);
        self::assertSame(2, $result['tables_processed']);
        self::assertCount(2, $result['sequences_fixed']);
        self::assertCount(2, $result['triggers_created']);

        // Verify changes were made
        $trigger1 = DB::selectOne('
            SELECT 1 FROM information_schema.triggers
            WHERE trigger_name = ?
        ', ['update_test_apply_1_updated_at']);
        self::assertNotNull($trigger1);

        $trigger2 = DB::selectOne('
            SELECT 1 FROM information_schema.triggers
            WHERE trigger_name = ?
        ', ['update_test_apply_2_updated_at']);
        self::assertNotNull($trigger2);

        // Verify sequences were fixed
        $nextId1 = DB::selectOne("SELECT nextval('test_apply_1_id_seq') as next_id")->next_id;
        self::assertGreaterThan(100, $nextId1);

        $nextId2 = DB::selectOne("SELECT nextval('test_apply_2_id_seq') as next_id")->next_id;
        self::assertGreaterThan(200, $nextId2);

        // Clean up
        Schema::dropIfExists('test_apply_2');
        Schema::dropIfExists('test_apply_1');
    }

    public function test_generate_standards_migration(): void
    {
        // Create tables that need standards
        Schema::create('test_migration_1', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('test_migration_2', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        // Insert data to cause sequence issues
        DB::table('test_migration_1')->insert(['id' => 500]);

        // Generate migration
        $migration = PgHelperLibrary::generateStandardsMigration('TestMigration');

        // Assert migration structure
        self::assertArrayHasKey('path', $migration);
        self::assertArrayHasKey('content', $migration);
        self::assertStringContainsString('database/migrations/', $migration['path']);
        self::assertStringContainsString('apply_postgresql_standards.php', $migration['path']);

        // Check migration content
        self::assertStringContainsString('PgHelperLibrary::applyTableStandards', $migration['content']);
        self::assertStringContainsString('test_migration_1', $migration['content']);
        self::assertStringContainsString('test_migration_2', $migration['content']);

        // Clean up
        Schema::dropIfExists('test_migration_2');
        Schema::dropIfExists('test_migration_1');
    }
}
