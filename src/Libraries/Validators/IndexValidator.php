<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

class IndexValidator
{
    /**
     * Validate required indexes exist.
     *
     * @param array<string> $existingIndexes
     * @param array<string> $requiredIndexes
     *
     * @return array<string> Warning messages
     */
    public static function validateRequired(
        string $tableName,
        array $existingIndexes,
        array $requiredIndexes
    ): array {
        return array_values(
            array_filter(
                array_map(
                    static fn (string $index): ?string => self::checkIndexExists($tableName, $index, $existingIndexes),
                    $requiredIndexes
                )
            )
        );
    }

    /**
     * Check if an index exists (supporting wildcards).
     *
     * @param array<string> $existingIndexes
     */
    private static function checkIndexExists(
        string $tableName,
        string $requiredIndex,
        array $existingIndexes
    ): ?string {
        $pattern = $tableName . '_' . $requiredIndex;

        return self::indexMatchesPattern($pattern, $existingIndexes)
            ? null
            : "Missing recommended index: {$requiredIndex}";
    }

    /**
     * Check if any existing index matches the pattern.
     *
     * @param array<string> $existingIndexes
     */
    private static function indexMatchesPattern(string $pattern, array $existingIndexes): bool
    {
        return [] !== array_filter(
            $existingIndexes,
            static fn (string $index): bool => fnmatch($pattern, $index)
        );
    }
}
