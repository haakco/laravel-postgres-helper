<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PgHelperLibrary
{
    protected static ?float $lastOperationTime = null;

    /**
     * @var array<string, mixed>
     */
    protected static array $operationStats = [];

    /**
     * @deprecated Use fixAll instead
     */
    public static function removeUpdatedAtFunction(): void
    {
        DB::statement(
            /* @lang POSTGRES-PSQL */
            'DROP FUNCTION IF EXISTS update_updated_at_column'
        );
    }

    public static function updateDateColumnsDefault(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/000010_update_date_columns_default.sql');
        if (false === $sql) {
            throw new \RuntimeException('Failed to read SQL file: 000010_update_date_columns_default.sql');
        }
        DB::unprepared($sql);
    }

    public static function addUpdateUpdatedAtColumn(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/000020_update_updated_at_column.sql');
        if (false === $sql) {
            throw new \RuntimeException('Failed to read SQL file: 000020_update_updated_at_column.sql');
        }
        DB::unprepared($sql);
    }

    public static function addUpdateUpdatedAtColumnForTables(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/000030_updated_at_column_for_tables.sql');
        if (false === $sql) {
            throw new \RuntimeException('Failed to read SQL file: 000030_updated_at_column_for_tables.sql');
        }
        DB::unprepared($sql);
    }

    public static function addFixAllSeq(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/000040_fix_all_seq.sql');
        if (false === $sql) {
            throw new \RuntimeException('Failed to read SQL file: 000040_fix_all_seq.sql');
        }
        DB::unprepared($sql);
    }

    public static function addFixDb(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/000050_fix_db.sql');
        if (false === $sql) {
            throw new \RuntimeException('Failed to read SQL file: 000050_fix_db.sql');
        }
        DB::unprepared($sql);
    }

    public static function fixAll(): void
    {
        $startTime = microtime(true);

        $sql = 'select public.fix_db()';
        DB::update($sql);

        self::recordOperationTime($startTime, 'fixAll');
    }

    /**
     * @deprecated Use fixAll instead
     */
    public static function addMissingUpdatedAtTriggers(): void
    {
        self::fixAll();
    }

    /**
     * @deprecated Use fixAll instead
     */
    public static function setUpdatedAtTrigger(string $tableName): void
    {
        self::fixAll();
    }

    /**
     * @deprecated Use fixAll instead
     */
    public static function setSequenceStart(string $tableName, ?int $startNo = null): void
    {
        self::fixAll();
    }

    /**
     * Fix sequences for specific tables only (much faster than fixAll).
     *
     * @param array<string>|null $tables Table names to fix, or null for all tables
     *
     * @return array{sequences_fixed: array<string>, time_taken: float}
     */
    public static function fixSequences(?array $tables = null): array
    {
        $startTime = microtime(true);
        $sequencesFixed = [];

        if (null === $tables) {
            // Fix all sequences
            $sql = "SELECT schemaname, tablename FROM pg_tables
                    WHERE schemaname NOT IN ('pg_catalog', 'information_schema')";
            $tables = DB::select($sql);
            $tables = array_map(static fn ($table) => $table->tablename, $tables);
        }

        foreach ($tables as $table) {
            // Check if table exists and has sequences
            $sequences = DB::select("
                SELECT seq.relname AS sequence_name
                FROM pg_class seq
                JOIN pg_depend dep ON seq.oid = dep.objid
                JOIN pg_class tbl ON dep.refobjid = tbl.oid
                WHERE seq.relkind = 'S'
                AND tbl.relname = ?
            ", [$table]);

            foreach ($sequences as $sequence) {
                $sequenceName = $sequence->sequence_name;

                // Get the column name associated with this sequence
                $columnInfo = DB::selectOne('
                    SELECT attname AS column_name
                    FROM pg_attribute
                    JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
                    JOIN pg_depend ON pg_depend.refobjid = pg_class.oid
                    JOIN pg_class seq ON seq.oid = pg_depend.objid
                    WHERE pg_class.relname = ?
                    AND seq.relname = ?
                    AND pg_attribute.attnum = pg_depend.refobjsubid
                ', [$table, $sequenceName]);

                if ($columnInfo) {
                    $columnName = $columnInfo->column_name;

                    // Fix the sequence
                    $maxValueResult = DB::selectOne("SELECT COALESCE(MAX({$columnName}), 0) as max_val FROM {$table}");
                    $maxValue = $maxValueResult->max_val ?? 0;

                    DB::statement("SELECT setval('{$sequenceName}', GREATEST({$maxValue}, 1), true)");
                    $sequencesFixed[] = $sequenceName;
                }
            }
        }

        $timeTaken = microtime(true) - $startTime;
        self::recordOperationTime($startTime, 'fixSequences', ['tables' => \count($tables)]);

        return [
            'sequences_fixed' => $sequencesFixed,
            'time_taken' => $timeTaken,
        ];
    }

    /**
     * Fix updated_at triggers for specific tables only.
     *
     * @param array<string>|null $tables Table names to fix, or null for all tables
     *
     * @return array{triggers_created: array<string>, triggers_skipped: array<string>, time_taken: float}
     */
    public static function fixTriggers(?array $tables = null): array
    {
        $startTime = microtime(true);
        $triggersCreated = [];
        $triggersSkipped = [];

        // Ensure the trigger function exists
        self::addUpdateUpdatedAtColumn();

        if (null === $tables) {
            // Get all tables with updated_at column
            $sql = "SELECT table_name FROM information_schema.columns
                    WHERE column_name = 'updated_at'
                    AND table_schema = 'public'";
            $tables = DB::select($sql);
            $tables = array_map(static fn ($table) => $table->table_name, $tables);
        }

        foreach ($tables as $table) {
            // Check if updated_at column exists
            $hasUpdatedAt = DB::selectOne("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = ?
                AND column_name = 'updated_at'
                AND table_schema = 'public'
            ", [$table]);

            if (!$hasUpdatedAt) {
                $triggersSkipped[] = $table;

                continue;
            }

            // Check if trigger already exists
            $triggerExists = DB::selectOne("
                SELECT 1 FROM information_schema.triggers
                WHERE trigger_name = ?
                AND event_object_table = ?
                AND trigger_schema = 'public'
            ", ["update_{$table}_updated_at", $table]);

            if (!$triggerExists) {
                // Create trigger
                DB::statement("
                    CREATE TRIGGER update_{$table}_updated_at
                    BEFORE UPDATE ON {$table}
                    FOR EACH ROW
                    EXECUTE PROCEDURE update_updated_at_column()
                ");
                $triggersCreated[] = $table;
            } else {
                $triggersSkipped[] = $table;
            }
        }

        $timeTaken = microtime(true) - $startTime;
        self::recordOperationTime($startTime, 'fixTriggers', ['tables' => \count($tables)]);

        return [
            'triggers_created' => $triggersCreated,
            'triggers_skipped' => $triggersSkipped,
            'time_taken' => $timeTaken,
        ];
    }

    /**
     * Check if a table has PostgreSQL standards applied.
     *
     * @param string $table Table name to check
     */
    public static function hasStandardsApplied(string $table): bool
    {
        $cacheKey = "postgres_helper_standards_{$table}";
        $cacheDuration = Config::get('postgreshelper.performance.cache_duration', 300);

        return Cache::remember($cacheKey, $cacheDuration, static function () use ($table): bool {
            // Check for updated_at trigger
            $hasTrigger = DB::selectOne("
                SELECT 1 FROM information_schema.triggers
                WHERE trigger_name = ?
                AND event_object_table = ?
                AND trigger_schema = 'public'
            ", ["update_{$table}_updated_at", $table]);

            // Check for proper sequence values
            $sequences = DB::select("
                SELECT seq.relname AS sequence_name
                FROM pg_class seq
                JOIN pg_depend dep ON seq.oid = dep.objid
                JOIN pg_class tbl ON dep.refobjid = tbl.oid
                WHERE seq.relkind = 'S'
                AND tbl.relname = ?
            ", [$table]);

            $sequencesOk = true;
            foreach ($sequences as $sequence) {
                $lastValue = DB::selectOne("SELECT last_value FROM {$sequence->sequence_name}");
                if ($lastValue && $lastValue->last_value < 1) {
                    $sequencesOk = false;

                    break;
                }
            }

            return (bool) $hasTrigger && $sequencesOk;
        });
    }

    /**
     * Apply all PostgreSQL standards to a specific table.
     *
     * @param string $table Table name
     */
    public static function applyTableStandards(string $table): void
    {
        $startTime = microtime(true);

        // Fix sequences for this table
        self::fixSequences([$table]);

        // Fix triggers for this table
        self::fixTriggers([$table]);

        // Clear cache
        Cache::forget("postgres_helper_standards_{$table}");

        self::recordOperationTime($startTime, 'applyTableStandards', ['table' => $table]);
    }

    /**
     * Get the execution time of the last operation.
     *
     * @return float|null Time in seconds, or null if no operation has been performed
     */
    public static function getLastOperationTime(): ?float
    {
        return self::$lastOperationTime;
    }

    /**
     * Get detailed statistics about operations.
     *
     * @return array{total_operations: int, operations: array<string, array{count: int, total_time: float, average_time: float, last_time: float}>}
     */
    public static function getOperationStats(): array
    {
        return [
            'total_operations' => array_sum(array_column(self::$operationStats, 'count')),
            'operations' => self::$operationStats,
        ];
    }

    /**
     * Validate database structure against configured rules.
     *
     * @param array<string>|null $tables Table names to validate, or null for all tables
     *
     * @return array{valid: bool, errors: array<string, array<string>>, warnings: array<string, array<string>>, tables_checked: int}
     */
    public static function validateStructure(?array $tables = null): array
    {
        $startTime = microtime(true);
        $errors = [];
        $warnings = [];
        $tablesChecked = 0;

        $validationRules = Config::get('postgreshelper.table_validations', []);

        if (null === $tables) {
            // Get all user tables
            $sql = "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
            $tables = DB::select($sql);
            $tables = array_map(static fn ($table) => $table->tablename, $tables);
        }

        foreach ($tables as $table) {
            $tablesChecked++;
            $tableErrors = [];
            $tableWarnings = [];

            // Find matching validation rules for this table
            $matchingRules = [];
            foreach ($validationRules as $pattern => $rules) {
                if (fnmatch($pattern, $table)) {
                    $matchingRules = array_merge_recursive($matchingRules, $rules);
                }
            }

            if ([] !== $matchingRules) {
                // Check required columns
                if (isset($matchingRules['required_columns'])) {
                    $columns = DB::select("
                        SELECT column_name
                        FROM information_schema.columns
                        WHERE table_name = ? AND table_schema = 'public'
                    ", [$table]);
                    $columnNames = array_map(static fn ($col) => $col->column_name, $columns);

                    foreach ($matchingRules['required_columns'] as $requiredColumn) {
                        if (!\in_array($requiredColumn, $columnNames, true)) {
                            $tableErrors[] = "Missing required column: {$requiredColumn}";
                        }
                    }
                }

                // Check required indexes
                if (isset($matchingRules['required_indexes'])) {
                    $indexes = DB::select("
                        SELECT indexname
                        FROM pg_indexes
                        WHERE tablename = ? AND schemaname = 'public'
                    ", [$table]);
                    $indexNames = array_map(static fn ($idx) => $idx->indexname, $indexes);

                    foreach ($matchingRules['required_indexes'] as $requiredIndex) {
                        // Handle wildcard index patterns
                        $found = false;
                        foreach ($indexNames as $indexName) {
                            if (fnmatch($table . '_' . $requiredIndex, $indexName)) {
                                $found = true;

                                break;
                            }
                        }
                        if (!$found) {
                            $tableWarnings[] = "Missing recommended index: {$requiredIndex}";
                        }
                    }
                }

                // Check column types
                if (isset($matchingRules['column_types'])) {
                    $columns = DB::select("
                        SELECT column_name, data_type, is_nullable
                        FROM information_schema.columns
                        WHERE table_name = ? AND table_schema = 'public'
                    ", [$table]);

                    foreach ($columns as $column) {
                        if (isset($matchingRules['column_types'][$column->column_name])) {
                            $expectedType = $matchingRules['column_types'][$column->column_name];

                            // Handle type aliases
                            $actualType = $column->data_type;
                            $typeMatches = [
                                'bigint' => ['bigint'],
                                'integer' => ['integer', 'int'],
                                'text' => ['text', 'character varying', 'varchar'],
                                'timestamp' => ['timestamp without time zone', 'timestamp with time zone'],
                                'boolean' => ['boolean', 'bool'],
                            ];

                            $isValidType = false;
                            if (isset($typeMatches[$expectedType])) {
                                $isValidType = \in_array($actualType, $typeMatches[$expectedType], true);
                            } else {
                                $isValidType = $actualType === $expectedType;
                            }

                            if (!$isValidType) {
                                $tableErrors[] = "Column '{$column->column_name}' has type '{$actualType}', expected '{$expectedType}'";
                            }
                        }
                    }
                }

                // Check constraints
                if (isset($matchingRules['required_constraints'])) {
                    $constraints = DB::select('
                        SELECT conname, contype
                        FROM pg_constraint
                        WHERE conrelid = ?::regclass
                    ', [$table]);

                    foreach ($matchingRules['required_constraints'] as $constraintName => $constraintType) {
                        $found = false;
                        foreach ($constraints as $constraint) {
                            if (fnmatch($constraintName, $constraint->conname) && $constraint->contype === $constraintType) {
                                $found = true;

                                break;
                            }
                        }
                        if (!$found) {
                            $tableErrors[] = "Missing required constraint: {$constraintName} (type: {$constraintType})";
                        }
                    }
                }
            }

            // Always check for PostgreSQL best practices
            // Check if sequences need fixing
            $sequences = DB::select("
                SELECT seq.relname AS sequence_name
                FROM pg_class seq
                JOIN pg_depend dep ON seq.oid = dep.objid
                JOIN pg_class tbl ON dep.refobjid = tbl.oid
                WHERE seq.relkind = 'S' AND tbl.relname = ?
            ", [$table]);

            foreach ($sequences as $sequence) {
                $lastValue = DB::selectOne("SELECT last_value FROM {$sequence->sequence_name}");
                if ($lastValue && $lastValue->last_value < 1) {
                    $tableWarnings[] = "Sequence '{$sequence->sequence_name}' may need to be reset";
                }
            }

            // Check for missing updated_at trigger
            $hasUpdatedAt = DB::selectOne("
                SELECT 1 FROM information_schema.columns
                WHERE table_name = ? AND column_name = 'updated_at' AND table_schema = 'public'
            ", [$table]);

            if ($hasUpdatedAt) {
                $hasTrigger = DB::selectOne('
                    SELECT 1 FROM information_schema.triggers
                    WHERE trigger_name = ? AND event_object_table = ?
                ', ["update_{$table}_updated_at", $table]);

                if (!$hasTrigger) {
                    $tableWarnings[] = "Table has 'updated_at' column but missing update trigger";
                }
            }

            if ([] !== $tableErrors) {
                $errors[$table] = $tableErrors;
            }
            if ([] !== $tableWarnings) {
                $warnings[$table] = $tableWarnings;
            }
        }
        self::recordOperationTime($startTime, 'validateStructure', ['tables' => $tablesChecked]);

        return [
            'valid' => [] === $errors,
            'errors' => $errors,
            'warnings' => $warnings,
            'tables_checked' => $tablesChecked,
        ];
    }

    /**
     * Run comprehensive health check on the database.
     *
     * @return array{overall_score: int, checks: array<string, array{status: string, message: string, score: int}>, recommendations: array<string>}
     */
    public static function runHealthCheck(): array
    {
        $startTime = microtime(true);
        $checks = [];
        $recommendations = [];
        $totalScore = 0;
        $maxScore = 0;

        // Check 1: Sequence health
        $sequenceCheck = self::checkSequenceHealth();
        $checks['sequences'] = $sequenceCheck;
        $totalScore += $sequenceCheck['score'];
        $maxScore += 100;

        // Check 2: Trigger health
        $triggerCheck = self::checkTriggerHealth();
        $checks['triggers'] = $triggerCheck;
        $totalScore += $triggerCheck['score'];
        $maxScore += 100;

        // Check 3: Table structure validation
        $structureCheck = self::checkStructureHealth();
        $checks['structure'] = $structureCheck;
        $totalScore += $structureCheck['score'];
        $maxScore += 100;

        // Check 4: Performance indicators
        $performanceCheck = self::checkPerformanceHealth();
        $checks['performance'] = $performanceCheck;
        $totalScore += $performanceCheck['score'];
        $maxScore += 100;

        // Check 5: Index health
        $indexCheck = self::checkIndexHealth();
        $checks['indexes'] = $indexCheck;
        $totalScore += $indexCheck['score'];
        $maxScore += 100;

        // Generate recommendations based on checks
        foreach ($checks as $check) {
            if ($check['score'] < 80 && isset($check['recommendation'])) {
                $recommendations[] = $check['recommendation'];
            }
        }

        $overallScore = (int) round(($totalScore / $maxScore) * 100);

        self::recordOperationTime($startTime, 'runHealthCheck');

        return [
            'overall_score' => $overallScore,
            'checks' => $checks,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Add the auto-apply standards function (used by event triggers).
     */
    public static function addAutoApplyStandards(): void
    {
        $sql = file_get_contents(__DIR__ . '/sql/000060_auto_apply_standards.sql');
        if (false === $sql) {
            throw new \RuntimeException('Failed to read SQL file: 000060_auto_apply_standards.sql');
        }
        DB::unprepared($sql);
    }

    /**
     * Enable event triggers to automatically apply standards to new tables.
     *
     * @param bool $enable Whether to enable or disable event triggers
     *
     * @return array{enabled: bool, message: string}
     */
    public static function enableEventTriggers(bool $enable = true): array
    {
        $startTime = microtime(true);

        try {
            if ($enable) {
                // Ensure functions exist
                self::addAutoApplyStandards();

                // Create the event trigger
                DB::statement("
                    CREATE EVENT TRIGGER auto_apply_standards_trigger
                    ON ddl_command_end
                    WHEN TAG IN ('CREATE TABLE')
                    EXECUTE FUNCTION auto_apply_table_standards()
                ");

                $message = 'Event triggers enabled - standards will be automatically applied to new tables';
            } else {
                // Drop the event trigger
                DB::statement('DROP EVENT TRIGGER IF EXISTS auto_apply_standards_trigger');
                $message = 'Event triggers disabled';
            }

            self::recordOperationTime($startTime, 'enableEventTriggers', ['enabled' => $enable]);

            return [
                'enabled' => $enable,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to configure event triggers', [
                'error' => $e->getMessage(),
                'enable' => $enable,
            ]);

            throw $e;
        }
    }

    /**
     * Check if event triggers are currently enabled.
     */
    public static function areEventTriggersEnabled(): bool
    {
        $result = DB::selectOne("
            SELECT 1 FROM pg_event_trigger
            WHERE evtname = 'auto_apply_standards_trigger'
            AND evtenabled != 'D'
        ");

        return null !== $result;
    }

    /**
     * Apply PostgreSQL best practices to a list of tables or all tables.
     *
     * @param array<string>|null $tables Table names to process, or null for all tables
     * @param bool $dryRun If true, only report what would be done without making changes
     *
     * @return array{tables_processed: int, sequences_fixed: array<string>, triggers_created: array<string>, dry_run: bool}
     */
    public static function applyBestPractices(?array $tables = null, bool $dryRun = false): array
    {
        $startTime = microtime(true);
        $tablesProcessed = 0;
        $allSequencesFixed = [];
        $allTriggersCreated = [];

        if (null === $tables) {
            // Get all user tables
            $sql = "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
            $tables = DB::select($sql);
            $tables = array_map(static fn ($table) => $table->tablename, $tables);
        }

        foreach ($tables as $table) {
            $tablesProcessed++;

            if (!$dryRun) {
                // Fix sequences
                $seqResult = self::fixSequences([$table]);
                $allSequencesFixed = array_merge($allSequencesFixed, $seqResult['sequences_fixed']);

                // Fix triggers
                $trigResult = self::fixTriggers([$table]);
                $allTriggersCreated = array_merge($allTriggersCreated, $trigResult['triggers_created']);
            } else {
                // Dry run - just check what needs to be done
                $validation = self::validateStructure([$table]);

                foreach ($validation['warnings'] as $tableWarnings) {
                    foreach ($tableWarnings as $warning) {
                        if (str_contains($warning, 'Sequence') && str_contains($warning, 'may need to be reset')) {
                            $allSequencesFixed[] = "{$table} (would fix)";
                        }
                        if (str_contains($warning, 'missing update trigger')) {
                            $allTriggersCreated[] = "{$table} (would create)";
                        }
                    }
                }
            }
        }

        self::recordOperationTime($startTime, 'applyBestPractices', [
            'tables' => $tablesProcessed,
            'dry_run' => $dryRun,
        ]);

        return [
            'tables_processed' => $tablesProcessed,
            'sequences_fixed' => $allSequencesFixed,
            'triggers_created' => $allTriggersCreated,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Generate a migration that applies PostgreSQL standards to existing tables.
     *
     * @param string|null $migrationName Custom migration name
     *
     * @return array{path: string, content: string}
     */
    public static function generateStandardsMigration(?string $migrationName = null): array
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_apply_postgresql_standards.php";

        // Get all tables that need standards applied
        $validation = self::validateStructure();
        $tablesNeedingStandards = [];

        foreach ($validation['warnings'] as $table => $warnings) {
            foreach ($warnings as $warning) {
                if (str_contains($warning, 'missing update trigger')
                    || str_contains($warning, 'may need to be reset')) {
                    $tablesNeedingStandards[] = $table;

                    break;
                }
            }
        }

        $tablesJson = json_encode(array_unique($tablesNeedingStandards));

        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use HaakCo\\PostgresHelper\\Libraries\\PgHelperLibrary;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Apply PostgreSQL standards to tables that need them
        \$tables = {$tablesJson};

        if (!empty(\$tables)) {
            echo "Applying PostgreSQL standards to " . count(\$tables) . " tables...\n";

            foreach (\$tables as \$table) {
                PgHelperLibrary::applyTableStandards(\$table);
                echo "  ✓ Applied standards to {\$table}\n";
            }
        } else {
            echo "All tables already have PostgreSQL standards applied.\n";
        }

        // Ensure all sequences are properly set
        PgHelperLibrary::fixAll();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Standards are best practices and should not be reversed
        // This migration is intentionally not reversible
    }
};
PHP;

        return [
            'path' => "database/migrations/{$filename}",
            'content' => $content,
        ];
    }

    /**
     * Check health of all sequences.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    protected static function checkSequenceHealth(): array
    {
        $sequences = DB::select("
            SELECT
                seq.relname AS sequence_name,
                tbl.relname AS table_name,
                last_value
            FROM pg_class seq
            JOIN pg_depend dep ON seq.oid = dep.objid
            JOIN pg_class tbl ON dep.refobjid = tbl.oid
            JOIN pg_sequences ps ON ps.sequencename = seq.relname
            WHERE seq.relkind = 'S'
            AND tbl.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public')
        ");

        $totalSequences = \count($sequences);
        $problemSequences = [];

        foreach ($sequences as $sequence) {
            // Check if sequence value is properly set
            if ($sequence->last_value < 1) {
                $problemSequences[] = $sequence->sequence_name;

                continue;
            }

            // Check if sequence is ahead of max value in table
            $columnInfo = DB::selectOne('
                SELECT attname AS column_name
                FROM pg_attribute
                JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
                JOIN pg_depend ON pg_depend.refobjid = pg_class.oid
                JOIN pg_class seq ON seq.oid = pg_depend.objid
                WHERE pg_class.relname = ?
                AND seq.relname = ?
                AND pg_attribute.attnum = pg_depend.refobjsubid
            ', [$sequence->table_name, $sequence->sequence_name]);

            if ($columnInfo) {
                $maxValue = DB::selectOne(
                    "SELECT COALESCE(MAX({$columnInfo->column_name}), 0) as max_val FROM {$sequence->table_name}"
                );

                if ($maxValue && $sequence->last_value < $maxValue->max_val) {
                    $problemSequences[] = $sequence->sequence_name;
                }
            }
        }

        $problemCount = \count($problemSequences);
        $score = $totalSequences > 0 ? (int) round((($totalSequences - $problemCount) / $totalSequences) * 100) : 100;

        $status = 0 === $problemCount ? 'healthy' : ($problemCount < 3 ? 'warning' : 'critical');
        $message = 0 === $problemCount
            ? "All {$totalSequences} sequences are properly configured"
            : "{$problemCount} of {$totalSequences} sequences need attention";

        $result = [
            'status' => $status,
            'message' => $message,
            'score' => $score,
        ];

        if ($problemCount > 0) {
            $result['details'] = ['problem_sequences' => $problemSequences];
            $result['recommendation'] = 'Run PgHelperLibrary::fixSequences() to fix sequence issues';
        }

        return $result;
    }

    /**
     * Check health of updated_at triggers.
     *
     * @return array{status: string, message: string, score: int, details?: array{missing_triggers?: array<string>}, recommendation?: string}
     */
    protected static function checkTriggerHealth(): array
    {
        // Get tables with updated_at column
        $tablesWithUpdatedAt = DB::select("
            SELECT table_name
            FROM information_schema.columns
            WHERE column_name = 'updated_at'
            AND table_schema = 'public'
        ");

        $totalTables = \count($tablesWithUpdatedAt);
        $missingTriggers = [];

        foreach ($tablesWithUpdatedAt as $table) {
            $hasTrigger = DB::selectOne('
                SELECT 1 FROM information_schema.triggers
                WHERE trigger_name = ?
                AND event_object_table = ?
            ', ["update_{$table->table_name}_updated_at", $table->table_name]);

            if (!$hasTrigger) {
                $missingTriggers[] = $table->table_name;
            }
        }

        $missingCount = \count($missingTriggers);
        $score = $totalTables > 0 ? (int) round((($totalTables - $missingCount) / $totalTables) * 100) : 100;

        $status = 0 === $missingCount ? 'healthy' : ($missingCount < 5 ? 'warning' : 'critical');
        $message = 0 === $missingCount
            ? "All {$totalTables} tables with updated_at have triggers"
            : "{$missingCount} of {$totalTables} tables missing updated_at triggers";

        $result = [
            'status' => $status,
            'message' => $message,
            'score' => $score,
        ];

        if ($missingCount > 0) {
            $result['details'] = ['missing_triggers' => $missingTriggers];
            $result['recommendation'] = 'Run PgHelperLibrary::fixTriggers() to add missing triggers';
        }

        return $result;
    }

    /**
     * Check structure health based on validation rules.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    protected static function checkStructureHealth(): array
    {
        $validation = self::validateStructure();

        $errorCount = \count($validation['errors']);
        $warningCount = \count($validation['warnings']);
        $tablesChecked = $validation['tables_checked'];

        // Calculate score (errors weight more than warnings)
        $score = 100;
        if ($tablesChecked > 0) {
            $errorPenalty = ($errorCount / $tablesChecked) * 50;
            $warningPenalty = ($warningCount / $tablesChecked) * 25;
            $score = max(0, (int) round(100 - $errorPenalty - $warningPenalty));
        }

        $status = 0 === $errorCount ? (0 === $warningCount ? 'healthy' : 'warning') : 'critical';
        $message = "Checked {$tablesChecked} tables: {$errorCount} errors, {$warningCount} warnings";

        $result = [
            'status' => $status,
            'message' => $message,
            'score' => $score,
        ];

        if ($errorCount > 0 || $warningCount > 0) {
            $result['details'] = [
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ];
            $result['recommendation'] = 'Review structure validation errors and update schema accordingly';
        }

        return $result;
    }

    /**
     * Check performance health indicators.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    protected static function checkPerformanceHealth(): array
    {
        $stats = self::getOperationStats();
        $score = 100;
        $issues = [];

        // Check for slow operations
        foreach ($stats['operations'] as $operation => $data) {
            if ($data['average_time'] > 1.0) { // Operations taking more than 1 second on average
                $score -= 20;
                $issues[] = "{$operation} averaging {$data['average_time']}s";
            }
        }

        // Check table sizes for performance concerns
        $largeTables = DB::select("
            SELECT
                schemaname,
                tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
                pg_total_relation_size(schemaname||'.'||tablename) AS size_bytes
            FROM pg_tables
            WHERE schemaname = 'public'
            AND pg_total_relation_size(schemaname||'.'||tablename) > 104857600 -- 100MB
            ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
        ");

        if (\count($largeTables) > 0) {
            $score -= min(30, \count($largeTables) * 10);
            foreach ($largeTables as $table) {
                $issues[] = "Large table: {$table->tablename} ({$table->size})";
            }
        }

        $score = max(0, $score);
        $status = $score >= 80 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical');
        $message = [] === $issues ? 'No performance concerns detected' : 'Performance issues detected';

        $result = [
            'status' => $status,
            'message' => $message,
            'score' => $score,
        ];

        if ([] !== $issues) {
            $result['details'] = ['issues' => $issues];
            $result['recommendation'] = 'Consider using selective operations for large tables and optimizing slow operations';
        }

        return $result;
    }

    /**
     * Check index health and usage.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    protected static function checkIndexHealth(): array
    {
        // Find unused indexes
        $unusedIndexes = DB::select("
            SELECT
                schemaname,
                tablename,
                indexname,
                idx_scan,
                pg_size_pretty(pg_relation_size(indexrelid)) AS index_size
            FROM pg_stat_user_indexes
            WHERE schemaname = 'public'
            AND idx_scan = 0
            AND indexrelname NOT LIKE '%_pkey'
            AND pg_relation_size(indexrelid) > 1048576 -- 1MB
        ");

        // Find duplicate indexes
        $duplicateIndexes = DB::select('
            SELECT
                indrelid::regclass AS table_name,
                array_agg(indexrelid::regclass) AS indexes
            FROM pg_index
            GROUP BY indrelid, indkey
            HAVING COUNT(*) > 1
        ');

        $unusedCount = \count($unusedIndexes);
        $duplicateCount = \count($duplicateIndexes);

        $score = 100;
        $score -= min(50, $unusedCount * 10);
        $score -= min(30, $duplicateCount * 15);
        $score = max(0, $score);

        $issues = [];
        if ($unusedCount > 0) {
            $issues[] = "{$unusedCount} unused indexes consuming space";
        }
        if ($duplicateCount > 0) {
            $issues[] = "{$duplicateCount} duplicate indexes found";
        }

        $status = $score >= 80 ? 'healthy' : ($score >= 60 ? 'warning' : 'critical');
        $message = [] === $issues ? 'Index configuration is optimal' : implode(', ', $issues);

        $result = [
            'status' => $status,
            'message' => $message,
            'score' => $score,
        ];

        if ([] !== $issues) {
            $result['details'] = [
                'unused_indexes' => array_map(static fn ($idx) => $idx->indexname, $unusedIndexes),
                'duplicate_indexes' => $duplicateIndexes,
            ];
            $result['recommendation'] = 'Review and remove unused or duplicate indexes to improve performance';
        }

        return $result;
    }

    /**
     * Record operation timing and statistics.
     *
     * @param array<string, mixed> $context
     */
    protected static function recordOperationTime(float $startTime, string $operation, array $context = []): void
    {
        $duration = microtime(true) - $startTime;
        self::$lastOperationTime = $duration;

        // Update statistics
        if (!isset(self::$operationStats[$operation])) {
            self::$operationStats[$operation] = [
                'count' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'last_time' => 0,
            ];
        }

        self::$operationStats[$operation]['count']++;
        self::$operationStats[$operation]['total_time'] += $duration;
        self::$operationStats[$operation]['average_time']
            = self::$operationStats[$operation]['total_time'] / self::$operationStats[$operation]['count'];
        self::$operationStats[$operation]['last_time'] = $duration;

        // Log slow operations
        if (Config::get('postgreshelper.performance.log_slow_operations', true)) {
            $threshold = Config::get('postgreshelper.performance.slow_operation_threshold', 1000) / 1000; // Convert ms to seconds

            if ($duration > $threshold) {
                Log::channel(Config::get('postgreshelper.logging.channel', 'daily'))
                    ->warning("Slow PostgreSQL helper operation: {$operation}", [
                        'duration' => $duration,
                        'context' => $context,
                    ])
                ;
            }
        }

        // Log successful operations if configured
        if (Config::get('postgreshelper.logging.log_success', false)) {
            Log::channel(Config::get('postgreshelper.logging.channel', 'daily'))
                ->info("PostgreSQL helper operation completed: {$operation}", [
                    'duration' => $duration,
                    'context' => $context,
                ])
            ;
        }
    }
}
