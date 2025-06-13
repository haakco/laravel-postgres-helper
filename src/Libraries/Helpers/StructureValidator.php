<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Helpers;

use HaakCo\PostgresHelper\Libraries\Traits\PostgresQueries;
use HaakCo\PostgresHelper\Libraries\Validators\ColumnValidator;
use HaakCo\PostgresHelper\Libraries\Validators\ConstraintValidator;
use HaakCo\PostgresHelper\Libraries\Validators\IndexValidator;
use HaakCo\PostgresHelper\Libraries\Validators\SequenceValidator;
use HaakCo\PostgresHelper\Libraries\Validators\TriggerValidator;
use Illuminate\Support\Facades\Config;

class StructureValidator
{
    use PostgresQueries;

    /**
     * Validate table structure.
     *
     * @param array<string> $errors
     * @param array<string> $warnings
     */
    public static function validateTable(string $table, array &$errors, array &$warnings): void
    {
        $rules = self::getMatchingRules($table);

        if ([] === $rules) {
            self::validateBestPractices($table, $warnings);

            return;
        }

        self::validateWithRules($table, $rules, $errors, $warnings);
        self::validateBestPractices($table, $warnings);
    }

    /**
     * Get validation rules that match the table name.
     *
     * @return array<string, mixed>
     */
    private static function getMatchingRules(string $table): array
    {
        $validationRules = Config::get('postgreshelper.table_validations', []);
        $matchingRules = [];

        foreach ($validationRules as $pattern => $rules) {
            if (fnmatch($pattern, $table)) {
                $matchingRules = array_merge_recursive($matchingRules, $rules);
            }
        }

        return $matchingRules;
    }

    /**
     * Validate table against specific rules.
     *
     * @param array<string, mixed> $rules
     * @param array<string> $errors
     * @param array<string> $warnings
     */
    private static function validateWithRules(
        string $table,
        array $rules,
        array &$errors,
        array &$warnings
    ): void {
        if (isset($rules['required_columns'])) {
            self::validateColumns($table, $rules, $errors);
        }

        if (isset($rules['required_indexes'])) {
            self::validateIndexes($table, $rules['required_indexes'], $warnings);
        }

        if (isset($rules['required_constraints'])) {
            self::validateConstraints($table, $rules['required_constraints'], $errors);
        }
    }

    /**
     * Validate columns for a table.
     *
     * @param array<string, mixed> $rules
     * @param array<string> $errors
     */
    private static function validateColumns(string $table, array $rules, array &$errors): void
    {
        $columns = self::getTableColumns($table);
        $columnNames = array_map(static fn (object $col) => $col->column_name, $columns);

        // Check required columns
        $columnErrors = ColumnValidator::validateRequired($columnNames, $rules['required_columns']);
        $errors = array_merge($errors, $columnErrors);

        // Check column types if specified
        if (isset($rules['column_types'])) {
            $typeErrors = ColumnValidator::validateTypes($columns, $rules['column_types']);
            $errors = array_merge($errors, $typeErrors);
        }
    }

    /**
     * Validate indexes for a table.
     *
     * @param array<string> $requiredIndexes
     * @param array<string> $warnings
     */
    private static function validateIndexes(string $table, array $requiredIndexes, array &$warnings): void
    {
        $indexes = self::getTableIndexes($table);
        $indexNames = array_map(static fn (object $idx) => $idx->indexname, $indexes);

        $indexWarnings = IndexValidator::validateRequired($table, $indexNames, $requiredIndexes);
        $warnings = array_merge($warnings, $indexWarnings);
    }

    /**
     * Validate constraints for a table.
     *
     * @param array<string, string> $requiredConstraints
     * @param array<string> $errors
     */
    private static function validateConstraints(string $table, array $requiredConstraints, array &$errors): void
    {
        $constraints = self::getTableConstraints($table);
        $constraintErrors = ConstraintValidator::validateRequired($constraints, $requiredConstraints);
        $errors = array_merge($errors, $constraintErrors);
    }

    /**
     * Validate PostgreSQL best practices.
     *
     * @param array<string> $warnings
     */
    private static function validateBestPractices(string $table, array &$warnings): void
    {
        self::validateSequenceHealth($table, $warnings);
        self::validateTriggerHealth($table, $warnings);
    }

    /**
     * Validate sequence health for a table.
     *
     * @param array<string> $warnings
     */
    private static function validateSequenceHealth(string $table, array &$warnings): void
    {
        $sequences = self::getTableSequences($table);
        $sequenceWarnings = SequenceValidator::validateSequences($sequences);
        $warnings = array_merge($warnings, $sequenceWarnings);
    }

    /**
     * Validate trigger health for a table.
     *
     * @param array<string> $warnings
     */
    private static function validateTriggerHealth(string $table, array &$warnings): void
    {
        $hasUpdatedAt = self::columnExists($table, 'updated_at');
        $triggerWarnings = TriggerValidator::validateUpdatedAtTrigger($table, $hasUpdatedAt);
        $warnings = array_merge($warnings, $triggerWarnings);
    }
}
