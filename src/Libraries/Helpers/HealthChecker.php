<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Helpers;

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use HaakCo\PostgresHelper\Libraries\Traits\OperationTiming;
use HaakCo\PostgresHelper\Libraries\Traits\PostgresQueries;
use Illuminate\Support\Facades\DB;

class HealthChecker
{
    use OperationTiming;
    use PostgresQueries;

    /**
     * Check health of all sequences.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    public static function checkSequenceHealth(): array
    {
        $sequences = self::getAllSequences();
        $problemSequences = self::findProblematicSequences($sequences);

        return self::buildHealthResult(
            'sequences',
            $sequences,
            $problemSequences,
            'Run PgHelperLibrary::fixSequences() to fix sequence issues'
        );
    }

    /**
     * Check health of updated_at triggers.
     *
     * @return array{status: string, message: string, score: int, details?: array{missing_triggers?: array<string>}, recommendation?: string}
     */
    public static function checkTriggerHealth(): array
    {
        $tablesWithUpdatedAt = self::getTablesWithColumn('updated_at');
        $missingTriggers = self::findMissingTriggers($tablesWithUpdatedAt);

        return self::buildTriggerHealthResult($tablesWithUpdatedAt, $missingTriggers);
    }

    /**
     * Check structure health based on validation rules.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    public static function checkStructureHealth(): array
    {
        $validation = PgHelperLibrary::validateStructure();

        return self::buildStructureHealthResult($validation);
    }

    /**
     * Check performance health indicators.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    public static function checkPerformanceHealth(): array
    {
        $issues = [];
        $score = 100;

        self::checkOperationPerformance($issues, $score);
        self::checkLargeTables($issues, $score);

        return self::buildPerformanceHealthResult($issues, $score);
    }

    /**
     * Check index health and usage.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    public static function checkIndexHealth(): array
    {
        $unusedIndexes = self::findUnusedIndexes();
        $duplicateIndexes = self::findDuplicateIndexes();

        return self::buildIndexHealthResult($unusedIndexes, $duplicateIndexes);
    }

    /**
     * Get all sequences with their information.
     *
     * @return array<object{sequence_name: string, table_name: string, last_value: int}>
     */
    private static function getAllSequences(): array
    {
        return DB::select("
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
    }

    /**
     * Find problematic sequences.
     *
     * @param array<object{sequence_name: string, table_name: string, last_value: int}> $sequences
     *
     * @return array<string>
     */
    private static function findProblematicSequences(array $sequences): array
    {
        $problems = [];

        foreach ($sequences as $sequence) {
            if (self::isSequenceProblematic($sequence)) {
                $problems[] = $sequence->sequence_name;
            }
        }

        return $problems;
    }

    /**
     * Check if a sequence is problematic.
     *
     * @param object{sequence_name: string, table_name: string, last_value: int} $sequence
     */
    private static function isSequenceProblematic(object $sequence): bool
    {
        if ($sequence->last_value < 1) {
            return true;
        }

        $columnInfo = self::getSequenceColumnInfo($sequence->table_name, $sequence->sequence_name);

        if (!$columnInfo) {
            return false;
        }

        $maxValue = DB::selectOne(
            "SELECT COALESCE(MAX({$columnInfo->column_name}), 0) as max_val FROM {$sequence->table_name}"
        );

        return $maxValue && $sequence->last_value < $maxValue->max_val;
    }

    /**
     * Find tables missing updated_at triggers.
     *
     * @param array<string> $tables
     *
     * @return array<string>
     */
    private static function findMissingTriggers(array $tables): array
    {
        return array_filter(
            $tables,
            static fn (string $table): bool => !self::triggerExists("update_{$table}_updated_at", $table)
        );
    }

    /**
     * Check operation performance.
     *
     * @param array<string> $issues
     */
    private static function checkOperationPerformance(array &$issues, int &$score): void
    {
        $stats = self::getOperationStats();

        foreach ($stats['operations'] as $operation => $data) {
            if ($data['average_time'] > 1.0) {
                $score -= 20;
                $issues[] = "{$operation} averaging {$data['average_time']}s";
            }
        }
    }

    /**
     * Check for large tables.
     *
     * @param array<string> $issues
     */
    private static function checkLargeTables(array &$issues, int &$score): void
    {
        $largeTables = DB::select("
            SELECT
                tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
            FROM pg_tables
            WHERE schemaname = 'public'
            AND pg_total_relation_size(schemaname||'.'||tablename) > 104857600
            ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
        ");

        foreach ($largeTables as $table) {
            $score -= min(10, 30 / max(1, \count($largeTables)));
            $issues[] = "Large table: {$table->tablename} ({$table->size})";
        }
    }

    /**
     * Find unused indexes.
     *
     * @return array<object{schemaname: string, tablename: string, indexname: string, idx_scan: int, index_size: string}>
     */
    private static function findUnusedIndexes(): array
    {
        return DB::select("
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
            AND pg_relation_size(indexrelid) > 1048576
        ");
    }

    /**
     * Find duplicate indexes.
     *
     * @return array<object{table_name: string, indexes: string}>
     */
    private static function findDuplicateIndexes(): array
    {
        return DB::select('
            SELECT
                indrelid::regclass AS table_name,
                array_agg(indexrelid::regclass) AS indexes
            FROM pg_index
            GROUP BY indrelid, indkey
            HAVING COUNT(*) > 1
        ');
    }

    /**
     * Build health result for sequences.
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    private static function buildHealthResult(
        string $type,
        array $items,
        array $problems,
        string $recommendation
    ): array {
        $total = \count($items);
        $problemCount = \count($problems);
        $score = $total > 0 ? (int) round((($total - $problemCount) / $total) * 100) : 100;

        $status = 0 === $problemCount ? 'healthy' : ($problemCount < 3 ? 'warning' : 'critical');
        $message = 0 === $problemCount
            ? "All {$total} {$type} are properly configured"
            : "{$problemCount} of {$total} {$type} need attention";

        $result = [
            'status' => $status,
            'message' => $message,
            'score' => $score,
        ];

        if ($problemCount > 0) {
            $result['details'] = ["problem_{$type}" => $problems];
            $result['recommendation'] = $recommendation;
        }

        return $result;
    }

    /**
     * Build trigger health result.
     *
     * @param array<string> $tablesWithUpdatedAt
     * @param array<string> $missingTriggers
     *
     * @return array{status: string, message: string, score: int, details?: array{missing_triggers?: array<string>}, recommendation?: string}
     */
    private static function buildTriggerHealthResult(array $tablesWithUpdatedAt, array $missingTriggers): array
    {
        $totalTables = \count($tablesWithUpdatedAt);
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
     * Build structure health result.
     *
     * @param array{valid: bool, errors: array<string, array<string>>, warnings: array<string, array<string>>, tables_checked: int} $validation
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    private static function buildStructureHealthResult(array $validation): array
    {
        $errorCount = \count($validation['errors']);
        $warningCount = \count($validation['warnings']);
        $tablesChecked = $validation['tables_checked'];

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
     * Build performance health result.
     *
     * @param array<string> $issues
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    private static function buildPerformanceHealthResult(array $issues, int $score): array
    {
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
     * Build index health result.
     *
     * @param array<object{schemaname: string, tablename: string, indexname: string, idx_scan: int, index_size: string}> $unusedIndexes
     * @param array<object{table_name: string, indexes: string}> $duplicateIndexes
     *
     * @return array{status: string, message: string, score: int, details?: array<string, mixed>, recommendation?: string}
     */
    private static function buildIndexHealthResult(array $unusedIndexes, array $duplicateIndexes): array
    {
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
}
