<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Traits;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait DatabaseSeeder
{
    /**
     * Create test tables with various configurations.
     */
    protected function createTestTables(): void
    {
        // Standard table with all expected columns
        Schema::create('test_standard', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Table without updated_at
        Schema::create('test_no_updated_at', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamp('created_at');
        });

        // Table with custom sequence
        Schema::create('test_custom_sequence', static function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->timestamps();
        });

        // Insert data with explicit IDs to test sequence fixing
        DB::table('test_standard')->insert(['id' => 1000, 'name' => 'Test 1']);
        DB::table('test_custom_sequence')->insert(['id' => 2000, 'code' => 'TEST001']);
    }

    /**
     * Create tables with various validation issues.
     */
    protected function createProblematicTables(): void
    {
        // Missing required columns
        DB::statement('CREATE TABLE test_missing_columns (id SERIAL PRIMARY KEY, name VARCHAR(255))');

        // Wrong column types
        Schema::create('test_wrong_types', static function (Blueprint $table): void {
            $table->id();
            $table->integer('name'); // Should be string
            $table->string('age'); // Should be integer
            $table->timestamps();
        });
    }

    /**
     * Create tables for performance testing.
     */
    protected function createPerformanceTables(): void
    {
        // Large table with many records
        Schema::create('test_large_table', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->timestamps();
            $table->index('title');
        });

        // Table with unused index
        Schema::create('test_unused_index', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('unused_field');
            $table->timestamps();
            $table->index('unused_field');
        });
    }

    /**
     * Clean up test tables.
     */
    protected function dropTestTables(): void
    {
        $tables = [
            'test_standard',
            'test_no_updated_at',
            'test_custom_sequence',
            'test_missing_columns',
            'test_wrong_types',
            'test_large_table',
            'test_unused_index',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
