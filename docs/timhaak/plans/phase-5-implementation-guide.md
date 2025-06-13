# Phase 5 Implementation Guide

## Phase 0: Code Cleanup and Testing Foundation

### Overview
This guide provides step-by-step instructions for implementing the missing features in laravel-postgres-helper, starting with critical code cleanup and testing infrastructure.

### 🎯 Goals
- Refactor code to follow functional programming principles
- Establish comprehensive testing infrastructure
- Prepare codebase for new features
- Achieve 90%+ test coverage

---

## Week 1: Foundation Work

### Day 1-2: Code Cleanup and Refactoring

#### 1. Extract Common Patterns

**Create `src/Libraries/Traits/PostgresQueries.php`:**
```php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Traits;

use Illuminate\Support\Facades\DB;

trait PostgresQueries
{
    /**
     * Get all user tables in the public schema.
     *
     * @return array<string>
     */
    protected static function getUserTables(): array
    {
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        return array_map(static fn($table) => $table->tablename, $tables);
    }

    /**
     * Get all tables that have a specific column.
     *
     * @param string $columnName
     * @return array<string>
     */
    protected static function getTablesWithColumn(string $columnName): array
    {
        $sql = "SELECT table_name FROM information_schema.columns
                WHERE column_name = ? AND table_schema = 'public'";
        $tables = DB::select($sql, [$columnName]);
        return array_map(static fn($table) => $table->table_name, $tables);
    }

    /**
     * Check if a trigger exists for a table.
     *
     * @param string $triggerName
     * @param string $tableName
     * @return bool
     */
    protected static function triggerExists(string $triggerName, string $tableName): bool
    {
        $result = DB::selectOne(
            "SELECT 1 FROM information_schema.triggers
             WHERE trigger_name = ? AND event_object_table = ? AND trigger_schema = 'public'",
            [$triggerName, $tableName]
        );
        
        return null !== $result;
    }

    /**
     * Get table columns with their properties.
     *
     * @param string $tableName
     * @return array<object>
     */
    protected static function getTableColumns(string $tableName): array
    {
        return DB::select(
            "SELECT column_name, data_type, is_nullable
             FROM information_schema.columns
             WHERE table_name = ? AND table_schema = 'public'",
            [$tableName]
        );
    }

    /**
     * Get table indexes.
     *
     * @param string $tableName
     * @return array<object>
     */
    protected static function getTableIndexes(string $tableName): array
    {
        return DB::select(
            "SELECT indexname FROM pg_indexes
             WHERE tablename = ? AND schemaname = 'public'",
            [$tableName]
        );
    }
}
```

**Create `src/Libraries/Traits/SqlFileLoader.php`:**
```php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Traits;

use HaakCo\PostgresHelper\Exceptions\PostgresHelperException;
use Illuminate\Support\Facades\DB;

trait SqlFileLoader
{
    /**
     * Load and execute a SQL file.
     *
     * @param string $filename
     * @throws PostgresHelperException
     */
    protected static function executeSqlFile(string $filename): void
    {
        $sql = self::loadSqlFile($filename);
        DB::unprepared($sql);
    }

    /**
     * Load SQL file contents.
     *
     * @param string $filename
     * @return string
     * @throws PostgresHelperException
     */
    protected static function loadSqlFile(string $filename): string
    {
        $path = __DIR__ . '/../sql/' . $filename;
        
        if (!file_exists($path)) {
            throw new PostgresHelperException("SQL file not found: {$filename}");
        }
        
        $sql = file_get_contents($path);
        
        if (false === $sql) {
            throw new PostgresHelperException("Failed to read SQL file: {$filename}");
        }
        
        return $sql;
    }
}
```

**Create `src/Libraries/Traits/OperationTiming.php`:**
```php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

trait OperationTiming
{
    /**
     * Execute an operation with timing.
     *
     * @param callable $operation
     * @param string $operationName
     * @param array<string, mixed> $context
     * @return mixed
     */
    protected static function withTiming(callable $operation, string $operationName, array $context = []): mixed
    {
        $startTime = microtime(true);
        
        try {
            $result = $operation();
            self::recordOperationTime($startTime, $operationName, $context);
            return $result;
        } catch (\Exception $e) {
            self::recordOperationTime($startTime, $operationName, array_merge($context, [
                'error' => $e->getMessage(),
                'failed' => true,
            ]));
            throw $e;
        }
    }

    /**
     * Record operation timing and statistics.
     * This method should be under 20 lines after refactoring.
     */
    protected static function recordOperationTime(float $startTime, string $operation, array $context = []): void
    {
        $duration = microtime(true) - $startTime;
        self::updateOperationStats($operation, $duration);
        self::logSlowOperation($operation, $duration, $context);
    }

    /**
     * Update operation statistics.
     */
    private static function updateOperationStats(string $operation, float $duration): void
    {
        self::$lastOperationTime = $duration;

        if (!isset(self::$operationStats[$operation])) {
            self::$operationStats[$operation] = [
                'count' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'last_time' => 0,
            ];
        }

        $stats = &self::$operationStats[$operation];
        $stats['count']++;
        $stats['total_time'] += $duration;
        $stats['average_time'] = $stats['total_time'] / $stats['count'];
        $stats['last_time'] = $duration;
    }

    /**
     * Log slow operations if configured.
     */
    private static function logSlowOperation(string $operation, float $duration, array $context): void
    {
        if (!Config::get('postgreshelper.performance.log_slow_operations', true)) {
            return;
        }

        $threshold = Config::get('postgreshelper.performance.slow_operation_threshold', 1000) / 1000;

        if ($duration > $threshold) {
            Log::channel(Config::get('postgreshelper.logging.channel', 'daily'))
                ->warning("Slow PostgreSQL helper operation: {$operation}", [
                    'duration' => $duration,
                    'context' => $context,
                ]);
        }
    }
}
```

#### 2. Break Down Large Methods

**Create `src/Libraries/Validators/` directory with specialized validators:**

```php
// src/Libraries/Validators/ColumnValidator.php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

class ColumnValidator
{
    /**
     * Type aliases for PostgreSQL data types.
     */
    private const TYPE_ALIASES = [
        'bigint' => ['bigint'],
        'integer' => ['integer', 'int'],
        'text' => ['text', 'character varying', 'varchar'],
        'timestamp' => ['timestamp without time zone', 'timestamp with time zone'],
        'boolean' => ['boolean', 'bool'],
    ];

    /**
     * Validate required columns exist.
     *
     * @param array<string> $existingColumns
     * @param array<string> $requiredColumns
     * @return array<string> Error messages
     */
    public static function validateRequired(array $existingColumns, array $requiredColumns): array
    {
        $errors = [];
        
        foreach ($requiredColumns as $required) {
            if (!in_array($required, $existingColumns, true)) {
                $errors[] = "Missing required column: {$required}";
            }
        }
        
        return $errors;
    }

    /**
     * Validate column types match expected types.
     *
     * @param array<object> $columns
     * @param array<string, string> $expectedTypes
     * @return array<string> Error messages
     */
    public static function validateTypes(array $columns, array $expectedTypes): array
    {
        $errors = [];
        
        foreach ($columns as $column) {
            if (!isset($expectedTypes[$column->column_name])) {
                continue;
            }
            
            $expected = $expectedTypes[$column->column_name];
            $actual = $column->data_type;
            
            if (!self::typeMatches($actual, $expected)) {
                $errors[] = "Column '{$column->column_name}' has type '{$actual}', expected '{$expected}'";
            }
        }
        
        return $errors;
    }

    /**
     * Check if actual type matches expected type (considering aliases).
     */
    private static function typeMatches(string $actual, string $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }
        
        if (isset(self::TYPE_ALIASES[$expected])) {
            return in_array($actual, self::TYPE_ALIASES[$expected], true);
        }
        
        return false;
    }
}
```

### Day 3-4: Testing Infrastructure

#### 1. Create Test Base Classes

**Create `tests/TestCase.php`:**
```php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests;

use HaakCo\PostgresHelper\PostgresHelperServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app): array
    {
        return [PostgresHelperServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => env('DB_TEST_HOST', '127.0.0.1'),
            'port' => env('DB_TEST_PORT', 5432),
            'database' => env('DB_TEST_DATABASE', 'postgres_helper_test'),
            'username' => env('DB_TEST_USERNAME', 'postgres'),
            'password' => env('DB_TEST_PASSWORD', ''),
        ]);
        
        // Package config
        $app['config']->set('postgreshelper', require __DIR__ . '/../config/postgreshelper.php');
    }
}
```

**Create `tests/Traits/DatabaseSeeder.php`:**
```php
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
        Schema::create('test_standard', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Table without updated_at
        Schema::create('test_no_updated_at', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamp('created_at');
        });

        // Table with custom sequence
        Schema::create('test_custom_sequence', function (Blueprint $table) {
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
        Schema::create('test_wrong_types', function (Blueprint $table) {
            $table->id();
            $table->integer('name'); // Should be string
            $table->string('age'); // Should be integer
            $table->timestamps();
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
        ];
        
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
```

#### 2. Create Unit Tests

**Create `tests/Unit/Validators/ColumnValidatorTest.php`:**
```php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Unit\Validators;

use HaakCo\PostgresHelper\Libraries\Validators\ColumnValidator;
use PHPUnit\Framework\TestCase;

final class ColumnValidatorTest extends TestCase
{
    /** @test */
    public function it_validates_required_columns(): void
    {
        $existing = ['id', 'name', 'created_at'];
        $required = ['id', 'name', 'created_at', 'updated_at'];
        
        $errors = ColumnValidator::validateRequired($existing, $required);
        
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('updated_at', $errors[0]);
    }

    /** @test */
    public function it_validates_column_types_with_aliases(): void
    {
        $columns = [
            (object)['column_name' => 'id', 'data_type' => 'bigint'],
            (object)['column_name' => 'name', 'data_type' => 'character varying'],
            (object)['column_name' => 'active', 'data_type' => 'bool'],
        ];
        
        $expectedTypes = [
            'id' => 'bigint',
            'name' => 'text', // Should match varchar
            'active' => 'boolean', // Should match bool
        ];
        
        $errors = ColumnValidator::validateTypes($columns, $expectedTypes);
        
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_detects_wrong_column_types(): void
    {
        $columns = [
            (object)['column_name' => 'age', 'data_type' => 'character varying'],
        ];
        
        $expectedTypes = ['age' => 'integer'];
        
        $errors = ColumnValidator::validateTypes($columns, $expectedTypes);
        
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("'age' has type 'character varying', expected 'integer'", $errors[0]);
    }
}
```

### Testing Strategy

1. **Unit Tests** (70% of tests)
   - Test individual methods in isolation
   - Mock database interactions where possible
   - Focus on logic and edge cases

2. **Integration Tests** (25% of tests)
   - Test with real PostgreSQL database
   - Verify SQL operations work correctly
   - Test transaction safety

3. **Feature Tests** (5% of tests)
   - End-to-end scenarios
   - Performance benchmarks
   - Multi-version compatibility

### Next Steps

1. Complete refactoring of PgHelperLibrary.php using the new traits
2. Write unit tests for each extracted component
3. Set up code coverage reporting
4. Create performance benchmarking suite

---

## Implementation Checklist

- [ ] Extract common SQL queries to trait
- [ ] Extract SQL file loading to trait  
- [ ] Extract operation timing to trait
- [ ] Break down validateStructure() method
- [ ] Create validator classes
- [ ] Set up test infrastructure
- [ ] Write unit tests for validators
- [ ] Write integration tests for main features
- [ ] Configure code coverage reporting
- [ ] Update CI/CD for coverage requirements