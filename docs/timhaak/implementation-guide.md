# Laravel PostgreSQL Helper - Implementation Guide

This guide provides step-by-step instructions for implementing the modernization plan for the laravel-postgres-helper package.

## 📋 Prerequisites

Before starting implementation:

1. **Development Environment**
   - PHP 8.3+ installed
   - PostgreSQL 13+ running locally
   - Composer 2.0+
   - Git configured

2. **Access Requirements**
   - GitHub repository access
   - Packagist publishing rights (for releases)
   - Test PostgreSQL database

3. **Review Documentation**
   - Read `CLAUDE.md` for project context
   - Review `modernization-plan.yaml` for full details
   - Understand current usage patterns

## 🚀 Phase 1: Foundation (Week 1)

### Day 1-2: Dependencies and Project Setup

**1. Create feature branch:**
```bash
git checkout -b feature/modernization-phase-1
```

**2. Update composer.json:**
```json
{
  "require": {
    "php": ">=8.3",
    "illuminate/database": "^10.0|^11.0|^12.0",
    "illuminate/support": "^10.0|^11.0|^12.0"
  },
  "require-dev": {
    "larastan/larastan": "^2.0",
    "laravel/pint": "^1.0",
    "orchestra/testbench": "^8.0|^9.0|^10.0",
    "phpunit/phpunit": "^10.0|^11.0",
    "roave/security-advisories": "dev-latest"
  },
  "scripts": {
    "lint": ["@pint", "@phpstan"],
    "test": "phpunit",
    "fix-all": ["@pint", "@test"],
    "pint": "./vendor/bin/pint",
    "phpstan": "./vendor/bin/phpstan analyse"
  }
}
```

**3. Remove old dependencies:**
```bash
composer remove barryvdh/laravel-ide-helper brainmaestro/composer-git-hooks ergebnis/composer-normalize
rm -f .ecs.php psalm.xml
```

**4. Install new dependencies:**
```bash
composer update
```

### Day 3: Linting Configuration

**1. Create pint.json:**
```json
{
  "preset": "laravel",
  "rules": {
    "binary_operator_spaces": true,
    "blank_line_after_namespace": true,
    "blank_line_after_opening_tag": true,
    "declare_strict_types": true
  }
}
```

**2. Update phpstan.neon:**
```yaml
includes:
  - vendor/larastan/larastan/extension.neon

parameters:
  paths:
    - src
    - tests
  level: 8
  checkGenericClassInNonGenericObjectType: false
```

**3. Fix existing code style:**
```bash
composer pint
composer phpstan
```

### Day 4: GitHub Actions Setup

**1. Create .github/workflows/tests.yml:**
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:${{ matrix.postgres }}
        env:
          POSTGRES_PASSWORD: password
          POSTGRES_DB: test_db
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports:
          - 5432:5432
    
    strategy:
      matrix:
        php: [8.3, 8.4]
        laravel: [10.*, 11.*, 12.*]
        postgres: [13, 14, 15, 16]
        exclude:
          - php: 8.3
            laravel: 12.*
            postgres: 13
    
    name: P${{ matrix.php }} - L${{ matrix.laravel }} - PG${{ matrix.postgres }}
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo, pdo_pgsql
          coverage: none
      
      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
          composer update --prefer-stable --prefer-dist --no-interaction
      
      - name: Run tests
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: test_db
          DB_USERNAME: postgres
          DB_PASSWORD: password
        run: composer test
      
      - name: Run linting
        run: composer lint
```

### Day 5: ACT Validation and Testing Setup

**1. Create scripts/validate-act-tests.sh:**
```bash
#!/bin/bash
set -e

echo "🧪 Validating ACT tests for laravel-postgres-helper..."
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if act is installed
if ! command -v act &> /dev/null; then
    echo -e "${RED}❌ FAIL: act is not installed!${NC}"
    echo "   Install: brew install act"
    exit 1
fi

# Check if act is in dry-run mode
if act --dry-run push &>/dev/null; then
    echo -e "${RED}❌ FAIL: act is in dry-run mode!${NC}"
    echo "   This prevents proper validation testing"
    echo "   Run: act push --rm"
    exit 1
fi

# Create test result directory
mkdir -p .act-test-results

# Run act tests
echo -e "${YELLOW}⚡ Running GitHub Actions with act...${NC}"
if act push --rm > .act-test-results/output.log 2>&1; then
    echo -e "${GREEN}✅ PASS: GitHub Actions workflow completed${NC}"
else
    echo -e "${RED}❌ FAIL: GitHub Actions workflow failed${NC}"
    echo "   Check .act-test-results/output.log for details"
    exit 1
fi

# Validate expected outputs
echo -e "${YELLOW}🔍 Validating test outputs...${NC}"

# Check for PHPUnit execution
if grep -q "PHPUnit" .act-test-results/output.log; then
    echo -e "${GREEN}✅ PHPUnit tests executed${NC}"
else
    echo -e "${RED}❌ PHPUnit tests not found${NC}"
    exit 1
fi

# Check for Pint execution
if grep -q "pint" .act-test-results/output.log; then
    echo -e "${GREEN}✅ Laravel Pint executed${NC}"
else
    echo -e "${RED}❌ Laravel Pint not found${NC}"
    exit 1
fi

# Check for PHPStan execution
if grep -q "phpstan" .act-test-results/output.log; then
    echo -e "${GREEN}✅ PHPStan analysis executed${NC}"
else
    echo -e "${RED}❌ PHPStan analysis not found${NC}"
    exit 1
fi

echo -e "${GREEN}✅ PASS: All ACT validation checks completed successfully!${NC}"
echo "=================================================="
echo "Ready to push to GitHub with confidence! 🚀"
```

**2. Make script executable:**
```bash
chmod +x scripts/validate-act-tests.sh
```

**3. Add to .gitignore:**
```bash
echo ".act-test-results/" >> .gitignore
```

## 💻 Phase 2: Core Enhancements (Week 2)

### Day 1-2: Enhanced PgHelperLibrary Methods

**1. Update src/Libraries/PgHelperLibrary.php:**
```php
<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PgHelperLibrary
{
    protected static array $operationStats = [];
    protected static float $lastOperationTime = 0.0;
    
    /**
     * Fix all sequences, triggers, and defaults (backward compatibility)
     */
    public static function fixAll(): void
    {
        $startTime = microtime(true);
        
        DB::select('SELECT public.fix_db()');
        
        self::$lastOperationTime = microtime(true) - $startTime;
        self::logOperation('fixAll', null, self::$lastOperationTime);
    }
    
    /**
     * Fix sequences for specific tables or all tables
     */
    public static function fixSequences(?array $tables = null): array
    {
        $startTime = microtime(true);
        $results = ['sequences_fixed' => [], 'errors' => []];
        
        if ($tables === null) {
            // Fix all sequences
            DB::select('SELECT public.fix_all_seq()');
            $results['sequences_fixed'][] = 'all';
        } else {
            // Fix specific table sequences
            foreach ($tables as $table) {
                try {
                    $sequences = self::getSequencesForTable($table);
                    foreach ($sequences as $sequence) {
                        DB::statement("SELECT setval('{$sequence['sequence_name']}', COALESCE(MAX({$sequence['column_name']}), 1), false) FROM {$table}");
                        $results['sequences_fixed'][] = $sequence['sequence_name'];
                    }
                } catch (\Exception $e) {
                    $results['errors'][$table] = $e->getMessage();
                }
            }
        }
        
        $results['time_taken'] = microtime(true) - $startTime;
        self::$lastOperationTime = $results['time_taken'];
        self::logOperation('fixSequences', $tables, $results['time_taken']);
        
        return $results;
    }
    
    /**
     * Fix updated_at triggers for specific tables or all tables
     */
    public static function fixTriggers(?array $tables = null): array
    {
        $startTime = microtime(true);
        $results = ['triggers_created' => [], 'triggers_skipped' => [], 'errors' => []];
        
        if ($tables === null) {
            // Fix all triggers
            DB::select('SELECT public.update_updated_at_column_for_tables()');
            $results['triggers_created'][] = 'all';
        } else {
            // Fix specific table triggers
            foreach ($tables as $table) {
                try {
                    if (self::tableHasColumn($table, 'updated_at')) {
                        if (!self::tableHasTrigger($table, 'updated_at')) {
                            self::createUpdatedAtTrigger($table);
                            $results['triggers_created'][] = $table;
                        } else {
                            $results['triggers_skipped'][] = $table;
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors'][$table] = $e->getMessage();
                }
            }
        }
        
        $results['time_taken'] = microtime(true) - $startTime;
        self::$lastOperationTime = $results['time_taken'];
        self::logOperation('fixTriggers', $tables, $results['time_taken']);
        
        return $results;
    }
    
    /**
     * Validate table structure against configured rules
     */
    public static function validateStructure(?array $tables = null): array
    {
        $config = config('postgreshelper.table_validations', []);
        $results = ['valid' => [], 'errors' => []];
        
        foreach ($config as $pattern => $rules) {
            $matchingTables = self::getTablesMatchingPattern($pattern, $tables);
            
            foreach ($matchingTables as $table) {
                $errors = self::validateTableAgainstRules($table, $rules);
                
                if (empty($errors)) {
                    $results['valid'][] = $table;
                } else {
                    $results['errors'][$table] = $errors;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Run comprehensive health check
     */
    public static function runHealthCheck(): array
    {
        $startTime = microtime(true);
        
        $results = [
            'sequences' => self::checkSequenceHealth(),
            'triggers' => self::checkTriggerHealth(),
            'constraints' => self::checkConstraintHealth(),
            'performance' => self::checkPerformanceHealth(),
            'overall_score' => 0,
            'time_taken' => 0
        ];
        
        // Calculate overall health score
        $totalChecks = 0;
        $passedChecks = 0;
        
        foreach ($results as $category => $checks) {
            if (is_array($checks) && isset($checks['passed'], $checks['total'])) {
                $totalChecks += $checks['total'];
                $passedChecks += $checks['passed'];
            }
        }
        
        $results['overall_score'] = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;
        $results['time_taken'] = microtime(true) - $startTime;
        
        return $results;
    }
    
    /**
     * Check if table has standards applied
     */
    public static function hasStandardsApplied(string $table): bool
    {
        $hasUpdatedAtTrigger = true;
        $hasProperDefaults = true;
        
        if (self::tableHasColumn($table, 'updated_at')) {
            $hasUpdatedAtTrigger = self::tableHasTrigger($table, 'updated_at');
        }
        
        if (self::tableHasColumn($table, 'created_at')) {
            $hasProperDefaults = self::columnHasDefault($table, 'created_at');
        }
        
        return $hasUpdatedAtTrigger && $hasProperDefaults;
    }
    
    /**
     * Apply all standards to a specific table
     */
    public static function applyTableStandards(string $table): void
    {
        // Fix sequences
        self::fixSequences([$table]);
        
        // Fix triggers
        self::fixTriggers([$table]);
        
        // Fix defaults
        self::fixTableDefaults($table);
    }
    
    /**
     * Get last operation execution time
     */
    public static function getLastOperationTime(): float
    {
        return self::$lastOperationTime;
    }
    
    /**
     * Get operation statistics
     */
    public static function getOperationStats(): array
    {
        return self::$operationStats;
    }
    
    // Protected helper methods...
    
    protected static function logOperation(string $operation, ?array $tables, float $timeTaken): void
    {
        $stats = [
            'operation' => $operation,
            'tables' => $tables,
            'time_taken' => $timeTaken,
            'timestamp' => now()->toIso8601String()
        ];
        
        self::$operationStats[] = $stats;
        
        // Log slow operations
        $threshold = config('postgreshelper.performance.slow_operation_threshold', 1000);
        if ($timeTaken * 1000 > $threshold) {
            Log::warning('Slow PostgreSQL helper operation', $stats);
        }
    }
    
    // ... Additional helper methods implementation
}
```

### Day 3: Configuration System

**1. Update config/postgreshelper.php:**
```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automatic Standards Application
    |--------------------------------------------------------------------------
    |
    | Control whether database event triggers automatically apply standards
    | when new tables are created. This includes updated_at triggers and
    | timestamp defaults.
    |
    */
    'auto_standards' => [
        'enable_event_triggers' => env('PG_AUTO_STANDARDS', true),
        'selective_fixing' => env('PG_SELECTIVE_FIXING', false),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Table Structure Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for table structures. Use wildcards (* at start
    | or end) to match multiple tables. Rules include required columns,
    | indexes, and forbidden columns.
    |
    */
    'table_validations' => [
        '*_types' => [
            'required_columns' => [
                'id' => 'bigint',
                'name' => 'string',
                'description' => 'text|nullable',
                'created_at' => 'timestamp',
                'updated_at' => 'timestamp'
            ],
            'required_indexes' => [
                'name_unique' => ['columns' => ['name'], 'unique' => true]
            ],
        ],
        
        'permissions*' => [
            'required_columns' => [
                'id' => 'bigint',
                'name' => 'string',
                'slug' => 'string'
            ],
            'auto_slug_index' => true
        ],
        
        'users' => [
            'required_columns' => [
                'id' => 'bigint',
                'email' => 'string',
                'email_verified_at' => 'timestamp|nullable'
            ],
            'forbidden_columns' => ['password_plain', 'ssn', 'credit_card']
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring and logging for database operations.
    | Slow operations are logged with details for optimization.
    |
    */
    'performance' => [
        'log_slow_operations' => env('PG_LOG_SLOW_OPS', true),
        'slow_operation_threshold' => env('PG_SLOW_OP_THRESHOLD', 1000), // milliseconds
        'enable_statistics' => env('PG_ENABLE_STATS', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configure health check thresholds and validation rules.
    |
    */
    'health_checks' => [
        'sequence_gap_threshold' => 1000, // Warn if sequence gaps exceed this
        'index_usage_threshold' => 0.1, // Warn if index usage below 10%
        'check_orphaned_records' => true,
        'check_missing_indexes' => true,
    ]
];
```

### Day 4-5: Testing Infrastructure

**1. Create tests/TestCase.php:**
```php
<?php

namespace HaakCo\PostgresHelper\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use HaakCo\PostgresHelper\PostgresHelperServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDatabase();
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            PostgresHelperServiceProvider::class,
        ];
    }
    
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres_helper_test'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);
    }
    
    protected function setUpDatabase(): void
    {
        // Run package migrations
        $this->artisan('migrate')->run();
        
        // Create test tables
        $this->createTestTables();
    }
    
    protected function createTestTables(): void
    {
        // Implementation for test table creation
    }
}
```

**2. Create tests/Integration/PgHelperLibraryTest.php:**
```php
<?php

namespace HaakCo\PostgresHelper\Tests\Integration;

use HaakCo\PostgresHelper\Tests\TestCase;
use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class PgHelperLibraryTest extends TestCase
{
    use DatabaseTransactions;
    
    public function test_fix_all_maintains_backward_compatibility(): void
    {
        // Insert with explicit ID
        DB::table('test_table')->insert(['id' => 100, 'name' => 'test']);
        
        // Run fixAll
        PgHelperLibrary::fixAll();
        
        // Next insert should work
        $result = DB::table('test_table')->insertGetId(['name' => 'test2']);
        
        $this->assertEquals(101, $result);
    }
    
    public function test_selective_sequence_fixing(): void
    {
        // Create multiple tables with sequences
        $this->createMultipleTestTables();
        
        // Fix only one table's sequence
        $result = PgHelperLibrary::fixSequences(['test_table_1']);
        
        $this->assertArrayHasKey('sequences_fixed', $result);
        $this->assertArrayHasKey('time_taken', $result);
        $this->assertContains('test_table_1_id_seq', $result['sequences_fixed']);
    }
    
    public function test_performance_monitoring(): void
    {
        PgHelperLibrary::fixAll();
        
        $time = PgHelperLibrary::getLastOperationTime();
        $stats = PgHelperLibrary::getOperationStats();
        
        $this->assertIsFloat($time);
        $this->assertGreaterThan(0, $time);
        $this->assertIsArray($stats);
        $this->assertNotEmpty($stats);
    }
    
    public function test_structure_validation(): void
    {
        // Configure validation rules
        config([
            'postgreshelper.table_validations' => [
                'test_*' => [
                    'required_columns' => ['id', 'name', 'created_at', 'updated_at']
                ]
            ]
        ]);
        
        $result = PgHelperLibrary::validateStructure(['test_table']);
        
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }
}
```

## 🔧 Phase 3: Advanced Features (Week 3)

### Implementation Tasks

1. **Structure Validation System**
   - Implement wildcard pattern matching
   - Create column type validation
   - Add index validation
   - Build foreign key checking

2. **Health Check System**
   - Sequence health monitoring
   - Trigger consistency checking
   - Constraint validation
   - Performance analysis

3. **Documentation**
   - API documentation with examples
   - Configuration reference
   - Migration guide from v3 to v4
   - Troubleshooting guide

## 🚀 Phase 4: Event Triggers (Week 4)

### Implementation Tasks

1. **Database Event Triggers**
   - Create DDL event trigger functions
   - Implement auto-standards application
   - Add configuration controls
   - Create override mechanisms

2. **Testing & Deployment**
   - Test with existing projects
   - Create rollback procedures
   - Document deployment process
   - Monitor for issues

## ✅ Quality Checklist

Before completing each phase:

- [ ] All tests passing with 90%+ coverage
- [ ] PHPStan level 8 compliance
- [ ] Laravel Pint formatting applied
- [ ] Documentation updated
- [ ] ACT validation passing
- [ ] Backward compatibility verified
- [ ] Performance benchmarks met
- [ ] Code reviewed by team

## 🚨 Common Issues & Solutions

### Issue: Tests failing with PostgreSQL connection
**Solution:** Ensure PostgreSQL is running and test database exists:
```bash
createdb postgres_helper_test
```

### Issue: PHPStan errors with Laravel facades
**Solution:** Use Larastan which understands Laravel magic:
```bash
composer require --dev larastan/larastan
```

### Issue: ACT tests not matching GitHub Actions
**Solution:** Ensure act is not in dry-run mode:
```bash
act push --rm
```

## 📝 Release Process

1. **Prepare Release**
   - Update CHANGELOG.md
   - Bump version in composer.json
   - Create migration guide if needed

2. **Final Testing**
   - Run full test suite
   - Test against real projects
   - Verify documentation

3. **Create Release**
   - Tag release on GitHub
   - Publish to Packagist
   - Announce to teams

## 🤝 Team Communication

- **Daily Updates**: Post progress in project channel
- **Blockers**: Raise immediately with team lead
- **Code Reviews**: Request within 24 hours of PR
- **Documentation**: Update as you code, not after

---

**Remember: This is critical infrastructure - test thoroughly and maintain backward compatibility!**