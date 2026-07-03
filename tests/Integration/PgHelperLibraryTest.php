<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Integration;

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use HaakCo\PostgresHelper\Tests\TestCase;
use HaakCo\PostgresHelper\Tests\Traits\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/**
 * @internal
 *
 * @coversNothing
 */
final class PgHelperLibraryTest extends TestCase
{
    use DatabaseSeeder;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTables();
    }

    #[\Override]
    protected function tearDown(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS private_helper_test CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS "_timescaledb_catalog" CASCADE');
        $this->dropTestTables();
        parent::tearDown();
    }

    public function test_it_fixes_sequences_after_explicit_id_insert(): void
    {
        // The test tables already have explicit IDs inserted (1000 and 2000)
        // Try to insert without ID - should fail before fix
        try {
            DB::table('test_standard')->insert(['name' => 'Test 2']);
            self::fail('Should have thrown duplicate key exception');
        } catch (\Exception $e) {
            self::assertStringContainsString('duplicate key', $e->getMessage());
        }

        // Fix sequences
        $result = PgHelperLibrary::fixSequences(['test_standard']);

        // Verify fix
        self::assertArrayHasKey('sequences_fixed', $result);
        self::assertContains('test_standard_id_seq', $result['sequences_fixed']);

        // Now insert should work
        $newId = DB::table('test_standard')->insertGetId(['name' => 'Test 2']);
        self::assertGreaterThan(1000, $newId);
    }

    public function test_it_creates_updated_at_triggers_for_tables(): void
    {
        // Verify trigger doesn't exist yet
        $triggerExists = DB::selectOne(
            "SELECT 1 FROM information_schema.triggers
             WHERE trigger_name = 'update_test_standard_updated_at'
             AND event_object_table = 'test_standard'"
        );
        self::assertNull($triggerExists);

        // Create triggers
        $result = PgHelperLibrary::fixTriggers(['test_standard']);

        // Verify results
        self::assertArrayHasKey('triggers_created', $result);
        self::assertContains('test_standard', $result['triggers_created']);

        // Verify trigger now exists
        $triggerExists = DB::selectOne(
            "SELECT 1 FROM information_schema.triggers
             WHERE trigger_name = 'update_test_standard_updated_at'
             AND event_object_table = 'test_standard'"
        );
        self::assertNotNull($triggerExists);

        // Test trigger functionality
        $record = DB::table('test_standard')->where('id', 1000)->first();
        self::assertNotNull($record);
        self::assertObjectHasProperty('updated_at', $record);
        /** @var \stdClass $record */
        $originalUpdatedAt = $record->updated_at;

        sleep(1); // Ensure timestamp difference

        DB::table('test_standard')->where('id', 1000)->update(['name' => 'Updated Test']);

        $updatedRecord = DB::table('test_standard')->where('id', 1000)->first();
        self::assertNotNull($updatedRecord);
        self::assertObjectHasProperty('updated_at', $updatedRecord);
        self::assertNotSame($originalUpdatedAt, $updatedRecord->updated_at); /* @phpstan-ignore-line */
    }

    public function test_it_validates_table_structure(): void
    {
        $this->createProblematicTables();

        // Set up validation rules
        config(['postgreshelper.table_validations' => [
            'test_*' => [
                'required_columns' => ['id', 'created_at', 'updated_at'],
            ],
        ]]);

        $result = PgHelperLibrary::validateStructure(['test_missing_columns']);

        self::assertFalse($result['valid']);
        self::assertArrayHasKey('test_missing_columns', $result['errors']);
        self::assertCount(2, $result['errors']['test_missing_columns']); // missing created_at and updated_at
    }

    public function test_it_runs_comprehensive_health_check(): void
    {
        $result = PgHelperLibrary::runHealthCheck();

        self::assertArrayHasKey('overall_score', $result);
        self::assertArrayHasKey('checks', $result);
        self::assertArrayHasKey('recommendations', $result);

        // Verify all health checks are present
        $expectedChecks = ['sequences', 'triggers', 'structure', 'performance', 'indexes'];
        foreach ($expectedChecks as $check) {
            self::assertArrayHasKey($check, $result['checks']);
            self::assertArrayHasKey('status', $result['checks'][$check]);
            self::assertArrayHasKey('score', $result['checks'][$check]);
            self::assertArrayHasKey('message', $result['checks'][$check]);
        }

        self::assertIsInt($result['overall_score']);
        self::assertGreaterThanOrEqual(0, $result['overall_score']);
        self::assertLessThanOrEqual(100, $result['overall_score']);
    }

    public function test_it_handles_fix_all_method_for_backward_compatibility(): void
    {
        // fixAll should work without errors (returns void)
        PgHelperLibrary::fixAll();

        // Verify sequences are fixed
        $newId = DB::table('test_standard')->insertGetId(['name' => 'After fixAll']);
        self::assertGreaterThan(1000, $newId);
    }

    public function test_fix_all_keeps_sequences_at_or_above_one_when_table_contains_negative_ids(): void
    {
        DB::table('test_standard')->truncate();
        DB::table('test_standard')->insert(['id' => -1, 'name' => 'Negative ID']);

        PgHelperLibrary::fixAll();

        $nextId = DB::selectOne("SELECT nextval('test_standard_id_seq') AS next_id");

        self::assertNotNull($nextId);
        self::assertGreaterThanOrEqual(1, $nextId->next_id);
    }

    public function test_fix_all_ignores_non_public_schema_tables(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS "_timescaledb_catalog"');
        DB::statement('CREATE TABLE "_timescaledb_catalog"."continuous_aggs_jobs_refresh_ranges" (id BIGSERIAL PRIMARY KEY, created_at TIMESTAMPTZ NULL)');

        PgHelperLibrary::fixAll();

        $column = DB::selectOne("
            SELECT column_default
            FROM information_schema.columns
            WHERE table_schema = '_timescaledb_catalog'
              AND table_name = 'continuous_aggs_jobs_refresh_ranges'
              AND column_name = 'created_at'
        ");

        self::assertNotNull($column);
        self::assertNull($column->column_default);
    }

    public function test_fix_all_does_not_repair_sequences_or_create_triggers_outside_public_schema(): void
    {
        DB::statement('CREATE SCHEMA private_helper_test');
        DB::statement('CREATE TABLE private_helper_test.sequence_probe (id BIGSERIAL PRIMARY KEY, name TEXT, updated_at TIMESTAMPTZ NULL)');
        DB::statement("INSERT INTO private_helper_test.sequence_probe (id, name) VALUES (1000, 'Manual ID')");

        PgHelperLibrary::fixAll();

        $trigger = DB::selectOne("
            SELECT 1
            FROM information_schema.triggers
            WHERE trigger_schema = 'private_helper_test'
              AND event_object_table = 'sequence_probe'
              AND trigger_name = 'update_sequence_probe_updated_at'
        ");
        $nextId = DB::selectOne("SELECT nextval('private_helper_test.sequence_probe_id_seq') AS next_id");

        self::assertNull($trigger);
        self::assertNotNull($nextId);
        self::assertLessThan(1000, $nextId->next_id);
    }

    public function test_it_skips_triggers_for_tables_without_updated_at(): void
    {
        $result = PgHelperLibrary::fixTriggers(['test_no_updated_at']);

        self::assertContains('test_no_updated_at', $result['triggers_skipped']);
        self::assertNotContains('test_no_updated_at', $result['triggers_created']);
    }
}
