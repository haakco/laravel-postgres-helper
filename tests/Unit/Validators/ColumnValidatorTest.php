<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Unit\Validators;

use HaakCo\PostgresHelper\Libraries\Validators\ColumnValidator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ColumnValidatorTest extends TestCase
{
    public function test_it_validates_required_columns(): void
    {
        $existing = ['id', 'name', 'created_at'];
        $required = ['id', 'name', 'created_at', 'updated_at'];

        $errors = ColumnValidator::validateRequired($existing, $required);

        self::assertCount(1, $errors);
        self::assertStringContainsString('updated_at', $errors[0]);
    }

    public function test_it_returns_no_errors_when_all_required_columns_exist(): void
    {
        $existing = ['id', 'name', 'created_at', 'updated_at'];
        $required = ['id', 'name', 'created_at', 'updated_at'];

        $errors = ColumnValidator::validateRequired($existing, $required);

        self::assertEmpty($errors);
    }

    public function test_it_validates_column_types_with_aliases(): void
    {
        $columns = [
            (object) ['column_name' => 'id', 'data_type' => 'bigint', 'is_nullable' => 'NO'],
            (object) ['column_name' => 'name', 'data_type' => 'character varying', 'is_nullable' => 'YES'],
            (object) ['column_name' => 'active', 'data_type' => 'bool', 'is_nullable' => 'NO'],
        ];

        $expectedTypes = [
            'id' => 'bigint',
            'name' => 'text', // Should match varchar
            'active' => 'boolean', // Should match bool
        ];

        $errors = ColumnValidator::validateTypes($columns, $expectedTypes);

        self::assertEmpty($errors);
    }

    public function test_it_detects_wrong_column_types(): void
    {
        $columns = [
            (object) ['column_name' => 'age', 'data_type' => 'character varying', 'is_nullable' => 'NO'],
        ];

        $expectedTypes = ['age' => 'integer'];

        $errors = ColumnValidator::validateTypes($columns, $expectedTypes);

        self::assertCount(1, $errors);
        self::assertStringContainsString("'age' has type 'character varying', expected 'integer'", $errors[0]);
    }

    public function test_it_ignores_columns_without_type_expectations(): void
    {
        $columns = [
            (object) ['column_name' => 'id', 'data_type' => 'bigint', 'is_nullable' => 'NO'],
            (object) ['column_name' => 'extra_field', 'data_type' => 'text', 'is_nullable' => 'YES'],
        ];

        $expectedTypes = [
            'id' => 'bigint',
        ];

        $errors = ColumnValidator::validateTypes($columns, $expectedTypes);

        self::assertEmpty($errors);
    }
}
