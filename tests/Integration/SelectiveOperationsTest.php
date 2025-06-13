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
final class SelectiveOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have the helper functions
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
    }

    public function test_fix_sequences_for_specific_tables(): void
    {
        // Create test tables
        Schema::create('test_users', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_posts', static function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        // Insert records with explicit IDs
        DB::table('test_users')->insert([
            ['id' => 100, 'name' => 'User 1'],
            ['id' => 200, 'name' => 'User 2'],
        ]);

        DB::table('test_posts')->insert([
            ['id' => 50, 'title' => 'Post 1'],
        ]);

        // Fix sequences for only test_users
        $result = PgHelperLibrary::fixSequences(['test_users']);

        // Assert
        self::assertArrayHasKey('sequences_fixed', $result);
        self::assertArrayHasKey('time_taken', $result);
        self::assertContains('test_users_id_seq', $result['sequences_fixed']);
        self::assertNotContains('test_posts_id_seq', $result['sequences_fixed']);
        self::assertIsFloat($result['time_taken']);
        self::assertLessThan(1, $result['time_taken']); // Should be fast

        // Verify sequence was actually fixed
        $nextId = DB::selectOne("SELECT nextval('test_users_id_seq') as next_id")->next_id;
        self::assertGreaterThan(200, $nextId);

        // Clean up
        Schema::dropIfExists('test_posts');
        Schema::dropIfExists('test_users');
    }

    public function test_fix_triggers_for_specific_tables(): void
    {
        // Create test tables
        Schema::create('test_products', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_categories', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('created_at');
            // No updated_at column
        });

        // Fix triggers for both tables
        $result = PgHelperLibrary::fixTriggers(['test_products', 'test_categories']);

        // Assert
        self::assertArrayHasKey('triggers_created', $result);
        self::assertArrayHasKey('triggers_skipped', $result);
        self::assertArrayHasKey('time_taken', $result);
        self::assertContains('test_products', $result['triggers_created']);
        self::assertContains('test_categories', $result['triggers_skipped']); // No updated_at column

        // Verify trigger exists
        $triggerExists = DB::selectOne("
            SELECT 1 FROM information_schema.triggers
            WHERE trigger_name = 'update_test_products_updated_at'
        ");
        self::assertNotNull($triggerExists);

        // Clean up
        Schema::dropIfExists('test_categories');
        Schema::dropIfExists('test_products');
    }

    public function test_has_standards_applied(): void
    {
        // Create table without standards
        Schema::create('test_items', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Initially should not have standards
        self::assertFalse(PgHelperLibrary::hasStandardsApplied('test_items'));

        // Apply standards
        PgHelperLibrary::applyTableStandards('test_items');

        // Now should have standards
        self::assertTrue(PgHelperLibrary::hasStandardsApplied('test_items'));

        // Clean up
        Schema::dropIfExists('test_items');
    }

    public function test_performance_tracking(): void
    {
        // Create a simple table
        Schema::create('test_performance', static function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        // Run an operation
        PgHelperLibrary::fixSequences(['test_performance']);

        // Check performance tracking
        $lastTime = PgHelperLibrary::getLastOperationTime();
        self::assertNotNull($lastTime);
        self::assertIsFloat($lastTime);
        self::assertLessThan(1, $lastTime); // Should be fast

        $stats = PgHelperLibrary::getOperationStats();
        self::assertArrayHasKey('total_operations', $stats);
        self::assertArrayHasKey('operations', $stats);
        self::assertArrayHasKey('fixSequences', $stats['operations']);
        self::assertSame(1, $stats['operations']['fixSequences']['count']);

        // Clean up
        Schema::dropIfExists('test_performance');
    }

    public function test_selective_operations_are_faster_than_fix_all(): void
    {
        // Create multiple tables
        for ($i = 1; $i <= 5; $i++) {
            Schema::create("test_table_{$i}", static function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        // Time selective operation (1 table)
        $startSelective = microtime(true);
        PgHelperLibrary::fixSequences(['test_table_1']);
        $selectiveTime = microtime(true) - $startSelective;

        // Time fixAll operation (all tables)
        $startAll = microtime(true);
        PgHelperLibrary::fixAll();
        $allTime = microtime(true) - $startAll;

        // Selective should be significantly faster
        self::assertLessThan($allTime, $selectiveTime);

        // Clean up
        for ($i = 1; $i <= 5; $i++) {
            Schema::dropIfExists("test_table_{$i}");
        }
    }
}
