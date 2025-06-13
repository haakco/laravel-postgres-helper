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

        return Cache::remember($cacheKey, $cacheDuration, static function () use ($table) {
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
