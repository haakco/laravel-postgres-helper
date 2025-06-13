<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

class ColumnValidator
{
    /**
     * Type aliases for PostgreSQL data types.
     *
     * @var array<string, array<string>>
     */
    private const array TYPE_ALIASES = [
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
     *
     * @return array<string> Error messages
     */
    public static function validateRequired(array $existingColumns, array $requiredColumns): array
    {
        return array_values(
            array_map(
                static fn (string $column): string => "Missing required column: {$column}",
                array_diff($requiredColumns, $existingColumns)
            )
        );
    }

    /**
     * Validate column types match expected types.
     *
     * @param array<object{column_name: string, data_type: string, is_nullable: string}> $columns
     * @param array<string, string> $expectedTypes
     *
     * @return array<string> Error messages
     */
    public static function validateTypes(array $columns, array $expectedTypes): array
    {
        return array_filter(
            array_map(
                static fn (object $column): ?string => self::validateColumnType($column, $expectedTypes),
                $columns
            )
        );
    }

    /**
     * Validate a single column type.
     *
     * @param object{column_name: string, data_type: string, is_nullable: string} $column
     * @param array<string, string> $expectedTypes
     */
    private static function validateColumnType(object $column, array $expectedTypes): ?string
    {
        if (!isset($expectedTypes[$column->column_name])) {
            return null;
        }

        $expected = $expectedTypes[$column->column_name];
        $actual = $column->data_type;

        return self::typeMatches($actual, $expected)
            ? null
            : "Column '{$column->column_name}' has type '{$actual}', expected '{$expected}'";
    }

    /**
     * Check if actual type matches expected type (considering aliases).
     */
    private static function typeMatches(string $actual, string $expected): bool
    {
        if ($actual === $expected) {
            return true;
        }

        return isset(self::TYPE_ALIASES[$expected])
            && \in_array($actual, self::TYPE_ALIASES[$expected], true);
    }
}
