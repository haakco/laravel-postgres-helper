<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

class ConstraintValidator
{
    /**
     * Validate required constraints exist.
     *
     * @param array<object{conname: string, contype: string}> $existingConstraints
     * @param array<string, string> $requiredConstraints
     *
     * @return array<string> Error messages
     */
    public static function validateRequired(
        array $existingConstraints,
        array $requiredConstraints
    ): array {
        return array_values(
            array_filter(
                array_map(
                    static fn (string $name, string $type): ?string => self::checkConstraintExists(
                        $name,
                        $type,
                        $existingConstraints
                    ),
                    array_keys($requiredConstraints),
                    $requiredConstraints
                )
            )
        );
    }

    /**
     * Check if a constraint exists.
     *
     * @param array<object{conname: string, contype: string}> $existingConstraints
     */
    private static function checkConstraintExists(
        string $constraintName,
        string $constraintType,
        array $existingConstraints
    ): ?string {
        $found = self::findMatchingConstraint($constraintName, $constraintType, $existingConstraints);

        return $found
            ? null
            : "Missing required constraint: {$constraintName} (type: {$constraintType})";
    }

    /**
     * Find a matching constraint.
     *
     * @param array<object{conname: string, contype: string}> $constraints
     */
    private static function findMatchingConstraint(
        string $name,
        string $type,
        array $constraints
    ): bool {
        return [] !== array_filter(
            $constraints,
            static fn (object $constraint): bool => fnmatch($name, $constraint->conname)
                && $constraint->contype === $type
        );
    }
}
