<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Unit\Validators;

use HaakCo\PostgresHelper\Libraries\Validators\IndexValidator;
use PHPUnit\Framework\TestCase;

final class IndexValidatorTest extends TestCase
{
    /** @test */
    public function it_validates_required_indexes_exist(): void
    {
        $tableName = 'users';
        $existingIndexes = ['users_pkey', 'users_email_unique', 'users_name_index'];
        $requiredIndexes = ['email_unique', 'name_index'];

        $warnings = IndexValidator::validateRequired($tableName, $existingIndexes, $requiredIndexes);

        $this->assertEmpty($warnings);
    }

    /** @test */
    public function it_detects_missing_indexes(): void
    {
        $tableName = 'users';
        $existingIndexes = ['users_pkey', 'users_email_unique'];
        $requiredIndexes = ['email_unique', 'name_index'];

        $warnings = IndexValidator::validateRequired($tableName, $existingIndexes, $requiredIndexes);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('name_index', $warnings[0]);
    }

    /** @test */
    public function it_handles_wildcard_patterns_in_index_names(): void
    {
        $tableName = 'permissions';
        $existingIndexes = ['permissions_pkey', 'permissions_name_unique_idx'];
        $requiredIndexes = ['name_unique*'];

        $warnings = IndexValidator::validateRequired($tableName, $existingIndexes, $requiredIndexes);

        $this->assertEmpty($warnings);
    }

    /** @test */
    public function it_returns_empty_array_when_no_indexes_required(): void
    {
        $tableName = 'logs';
        $existingIndexes = ['logs_pkey'];
        $requiredIndexes = [];

        $warnings = IndexValidator::validateRequired($tableName, $existingIndexes, $requiredIndexes);

        $this->assertEmpty($warnings);
    }
}