<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

use Illuminate\Support\Facades\DB;

class SequenceValidator
{
    /**
     * Check if sequences need fixing.
     *
     * @param array<object{sequence_schema?: string, sequence_name: string, table_schema?: string, table_name?: string, column_name?: string}> $sequences
     *
     * @return array<string> Warning messages
     */
    public static function validateSequences(array $sequences): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (object $sequence): ?string => self::checkSequence($sequence),
                    $sequences
                )
            )
        );
    }

    /**
     * Check a single sequence.
     *
     * @param object{sequence_schema?: string, sequence_name: string, table_schema?: string, table_name?: string, column_name?: string} $sequence
     */
    private static function checkSequence(object $sequence): ?string
    {
        $sequenceSchema = $sequence->sequence_schema ?? 'public';
        $lastValue = self::getSequenceLastValue($sequenceSchema, $sequence->sequence_name);

        if ($lastValue < 1) {
            return "Sequence '{$sequence->sequence_name}' may need to be reset";
        }

        if (isset($sequence->table_name, $sequence->column_name)
            && $lastValue < self::getColumnMaxValue($sequence->table_schema ?? 'public', $sequence->table_name, $sequence->column_name)) {
            return "Sequence '{$sequence->sequence_name}' may need to be reset";
        }

        return null;
    }

    /**
     * Get the last value of a sequence.
     */
    private static function getSequenceLastValue(string $schemaName, string $sequenceName): int
    {
        $result = DB::selectOne(
            'SELECT last_value FROM ' . self::quoteIdentifier($schemaName) . '.' . self::quoteIdentifier($sequenceName)
        );

        return $result ? (int) $result->last_value : 0;
    }

    /**
     * Get the highest explicit value currently stored for a sequence-backed column.
     */
    private static function getColumnMaxValue(string $schemaName, string $tableName, string $columnName): int
    {
        $result = DB::selectOne(\sprintf(
            'SELECT COALESCE(MAX(%s), 0) AS max_value FROM %s',
            self::quoteIdentifier($columnName),
            self::quoteIdentifier($schemaName) . '.' . self::quoteIdentifier($tableName)
        ));

        return $result ? (int) $result->max_value : 0;
    }

    /**
     * Quote a PostgreSQL identifier.
     */
    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
