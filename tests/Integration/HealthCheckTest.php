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
final class HealthCheckTest extends TestCase
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
    }

    public function test_run_health_check_on_healthy_database(): void
    {
        // Create properly configured tables
        Schema::create('test_healthy_1', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->timestamps();
        });

        Schema::create('test_healthy_2', static function (Blueprint $table): void {
            $table->id();
            $table->text('description');
            $table->timestamps();
        });

        // Apply standards to ensure triggers are created
        PgHelperLibrary::applyTableStandards('test_healthy_1');
        PgHelperLibrary::applyTableStandards('test_healthy_2');

        // Run health check
        $result = PgHelperLibrary::runHealthCheck();

        // Assert overall structure
        self::assertArrayHasKey('overall_score', $result);
        self::assertArrayHasKey('checks', $result);
        self::assertArrayHasKey('recommendations', $result);

        // Check individual health checks exist
        self::assertArrayHasKey('sequences', $result['checks']);
        self::assertArrayHasKey('triggers', $result['checks']);
        self::assertArrayHasKey('structure', $result['checks']);
        self::assertArrayHasKey('performance', $result['checks']);
        self::assertArrayHasKey('indexes', $result['checks']);

        // Each check should have required fields
        foreach ($result['checks'] as $check) {
            self::assertArrayHasKey('status', $check);
            self::assertArrayHasKey('message', $check);
            self::assertArrayHasKey('score', $check);
            self::assertIsString($check['status']);
            self::assertIsString($check['message']);
            self::assertIsInt($check['score']);
            self::assertGreaterThanOrEqual(0, $check['score']);
            self::assertLessThanOrEqual(100, $check['score']);
        }

        // Overall score should be reasonable for healthy database
        self::assertGreaterThan(50, $result['overall_score']);

        // Clean up
        Schema::dropIfExists('test_healthy_2');
        Schema::dropIfExists('test_healthy_1');
    }

    public function test_health_check_detects_sequence_issues(): void
    {
        // Create table with sequence issue
        Schema::create('test_seq_issue', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        // Insert with high ID to create sequence mismatch
        DB::table('test_seq_issue')->insert([
            'id' => 1000,
            'name' => 'Test',
        ]);

        // Run health check
        $result = PgHelperLibrary::runHealthCheck();

        // Sequence check should not be healthy
        self::assertNotSame('healthy', $result['checks']['sequences']['status']);
        self::assertLessThan(100, $result['checks']['sequences']['score']);

        // Should have recommendation
        self::assertNotEmpty($result['recommendations']);
        $hasSeqRecommendation = false;
        foreach ($result['recommendations'] as $recommendation) {
            if (str_contains($recommendation, 'fixSequences')) {
                $hasSeqRecommendation = true;

                break;
            }
        }
        self::assertTrue($hasSeqRecommendation);

        // Clean up
        Schema::dropIfExists('test_seq_issue');
    }

    public function test_health_check_detects_missing_triggers(): void
    {
        // Create tables without applying standards
        Schema::create('test_no_trigger_1', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('test_no_trigger_2', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        // Run health check
        $result = PgHelperLibrary::runHealthCheck();

        // Trigger check should not be healthy
        $triggerCheck = $result['checks']['triggers'];
        self::assertNotSame('healthy', $triggerCheck['status']);
        self::assertLessThan(100, $triggerCheck['score']);
        self::assertArrayHasKey('details', $triggerCheck);
        // @phpstan-ignore-next-line
        self::assertArrayHasKey('missing_triggers', $triggerCheck['details']);

        // Should recommend fixing triggers
        $hasTriggerRecommendation = false;
        foreach ($result['recommendations'] as $recommendation) {
            if (str_contains($recommendation, 'fixTriggers')) {
                $hasTriggerRecommendation = true;

                break;
            }
        }
        self::assertTrue($hasTriggerRecommendation);

        // Clean up
        Schema::dropIfExists('test_no_trigger_2');
        Schema::dropIfExists('test_no_trigger_1');
    }

    public function test_health_check_detects_unused_indexes(): void
    {
        // Create table with multiple indexes
        Schema::create('test_indexes', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index('idx_name');
            $table->string('email')->index('idx_email');
            $table->string('phone')->index('idx_phone_unused');
            $table->timestamps();
            $table->index(['name', 'email'], 'idx_composite');
        });

        // Insert some data
        for ($i = 1; $i <= 100; $i++) {
            DB::table('test_indexes')->insert([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'phone' => "555-{$i}",
            ]);
        }

        // Use some indexes but not others
        DB::table('test_indexes')->where('name', 'User 1')->first();
        DB::table('test_indexes')->where('email', 'user1@example.com')->first();
        // Don't use phone index

        // Note: Index usage statistics might not be immediately available in test environment
        // This test mainly verifies the health check runs without errors

        $result = PgHelperLibrary::runHealthCheck();

        // Index check should exist and have valid structure
        $indexCheck = $result['checks']['indexes'];
        self::assertArrayHasKey('status', $indexCheck);
        self::assertArrayHasKey('message', $indexCheck);
        self::assertArrayHasKey('score', $indexCheck);

        // Clean up
        Schema::dropIfExists('test_indexes');
    }

    public function test_health_check_performance_scoring(): void
    {
        // Create a large-ish table to trigger performance concerns
        Schema::create('test_large_table', static function (Blueprint $table): void {
            $table->id();
            $table->text('data');
            $table->timestamps();
        });

        // Note: Creating truly large tables in tests is impractical
        // This test verifies the performance check runs correctly

        // Run some operations to generate statistics
        PgHelperLibrary::fixSequences(['test_large_table']);
        PgHelperLibrary::fixTriggers(['test_large_table']);

        // Run health check
        $result = PgHelperLibrary::runHealthCheck();

        // Performance check should exist
        $perfCheck = $result['checks']['performance'];
        self::assertArrayHasKey('status', $perfCheck);
        self::assertArrayHasKey('score', $perfCheck);

        // Clean up
        Schema::dropIfExists('test_large_table');
    }

    public function test_health_check_overall_scoring(): void
    {
        // Run health check on empty/minimal database
        $result = PgHelperLibrary::runHealthCheck();

        // Overall score should be 0-100
        self::assertGreaterThanOrEqual(0, $result['overall_score']);
        self::assertLessThanOrEqual(100, $result['overall_score']);

        // Should have exactly 5 checks
        self::assertCount(5, $result['checks']);

        // Each check contributes to overall score
        $totalScore = 0;
        foreach ($result['checks'] as $check) {
            $totalScore += $check['score'];
        }
        $expectedOverall = (int) round($totalScore / 5);

        // Allow small rounding differences
        self::assertEqualsWithDelta($expectedOverall, $result['overall_score'], 1);
    }

    public function test_health_check_with_structure_validation_errors(): void
    {
        // Configure validation rules that will fail
        config(['postgreshelper.table_validations' => [
            'test_*' => [
                'required_columns' => ['required_field'],
            ],
        ]]);

        // Create table without required field
        Schema::create('test_validation_fail', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        // Run health check
        $result = PgHelperLibrary::runHealthCheck();

        // Structure check should not be healthy
        $structureCheck = $result['checks']['structure'];
        self::assertNotSame('healthy', $structureCheck['status']);
        self::assertLessThan(100, $structureCheck['score']);

        // Should have recommendation about structure
        $hasStructureRecommendation = false;
        foreach ($result['recommendations'] as $recommendation) {
            if (str_contains($recommendation, 'structure validation')) {
                $hasStructureRecommendation = true;

                break;
            }
        }
        self::assertTrue($hasStructureRecommendation);

        // Clean up
        Schema::dropIfExists('test_validation_fail');
        config(['postgreshelper.table_validations' => []]);
    }
}
