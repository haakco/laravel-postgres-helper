<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Tests\Integration;

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use HaakCo\PostgresHelper\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @internal
 *
 * @coversNothing
 */
final class StructureValidationTest extends TestCase
{
    use DatabaseTransactions;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have the helper functions
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
    }

    public function test_validate_structure_with_configured_rules(): void
    {
        // Configure validation rules
        Config::set('postgreshelper.table_validations', [
            '*_types' => [
                'required_columns' => ['id', 'name', 'created_at', 'updated_at'],
                'required_indexes' => ['name_unique'],
                'column_types' => [
                    'id' => 'bigint',
                    'name' => 'text',
                ],
            ],
            'test_users' => [
                'required_columns' => ['email'],
                'required_constraints' => [
                    'test_users_email_unique' => 'u',
                ],
            ],
        ]);

        // Create test tables
        Schema::create('test_types', static function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->timestamps();
            // Missing unique index on name
        });

        Schema::create('test_users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('test_products', static function (Blueprint $table): void {
            $table->id();
            $table->string('title'); // Different from expected 'name'
            $table->timestamps();
        });

        // Run validation
        $result = PgHelperLibrary::validateStructure();

        // Assert
        self::assertFalse($result['valid']);
        self::assertArrayHasKey('errors', $result);
        self::assertArrayHasKey('warnings', $result);
        self::assertSame(3, $result['tables_checked']);

        // Check specific errors
        self::assertArrayHasKey('test_products', $result['errors']);
        self::assertContains('Missing required column: name', $result['errors']['test_products']);

        // Check warnings
        self::assertArrayHasKey('test_types', $result['warnings']);
        self::assertContains('Missing recommended index: name_unique', $result['warnings']['test_types']);

        // Clean up
        Schema::dropIfExists('test_products');
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_types');
    }

    public function test_validate_structure_for_specific_tables(): void
    {
        // Create test tables
        Schema::create('test_validate_1', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('test_validate_2', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        // Validate only one table
        $result = PgHelperLibrary::validateStructure(['test_validate_1']);

        self::assertSame(1, $result['tables_checked']);
        self::assertTrue($result['valid']); // No rules configured, so valid

        // Clean up
        Schema::dropIfExists('test_validate_2');
        Schema::dropIfExists('test_validate_1');
    }

    public function test_validate_structure_detects_missing_triggers(): void
    {
        // Create table with updated_at but no trigger
        Schema::create('test_no_trigger', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        // Validate
        $result = PgHelperLibrary::validateStructure(['test_no_trigger']);

        // Should have warning about missing trigger
        self::assertArrayHasKey('test_no_trigger', $result['warnings']);
        $warnings = $result['warnings']['test_no_trigger'];
        self::assertContains("Table has 'updated_at' column but missing update trigger", $warnings);

        // Clean up
        Schema::dropIfExists('test_no_trigger');
    }

    public function test_validate_structure_with_wildcard_patterns(): void
    {
        // Configure validation with wildcard
        Config::set('postgreshelper.table_validations', [
            'test_*' => [
                'required_columns' => ['status'],
            ],
            'test_special_*' => [
                'required_columns' => ['special_field'],
            ],
        ]);

        // Create tables
        Schema::create('test_general', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Missing 'status' column
        });

        Schema::create('test_special_one', static function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            // Missing 'special_field' column
        });

        // Validate
        $result = PgHelperLibrary::validateStructure();

        // Both tables should have errors
        self::assertArrayHasKey('test_general', $result['errors']);
        self::assertContains('Missing required column: status', $result['errors']['test_general']);

        self::assertArrayHasKey('test_special_one', $result['errors']);
        self::assertContains('Missing required column: special_field', $result['errors']['test_special_one']);

        // Clean up
        Schema::dropIfExists('test_special_one');
        Schema::dropIfExists('test_general');
    }

    public function test_column_type_validation(): void
    {
        // Configure column type expectations
        Config::set('postgreshelper.table_validations', [
            'test_types_table' => [
                'column_types' => [
                    'id' => 'bigint',
                    'name' => 'text',
                    'is_active' => 'boolean',
                    'created_at' => 'timestamp',
                ],
            ],
        ]);

        // Create table with different types
        Schema::create('test_types_table', static function (Blueprint $table): void {
            $table->integer('id')->primary(); // integer instead of bigint
            $table->string('name'); // varchar instead of text
            $table->boolean('is_active'); // correct
            $table->timestamps(); // correct
        });

        // Validate
        $result = PgHelperLibrary::validateStructure(['test_types_table']);

        // Should have type mismatch errors
        self::assertArrayHasKey('test_types_table', $result['errors']);
        $errors = $result['errors']['test_types_table'];

        // Check for specific type errors
        $hasIdError = false;
        foreach ($errors as $error) {
            if (str_contains($error, "Column 'id' has type")) {
                $hasIdError = true;

                break;
            }
        }
        self::assertTrue($hasIdError, 'Should have error about id column type');

        // Clean up
        Schema::dropIfExists('test_types_table');
    }

    public function test_constraint_validation(): void
    {
        // Configure constraint requirements
        Config::set('postgreshelper.table_validations', [
            'test_constraints' => [
                'required_constraints' => [
                    '*_pkey' => 'p', // primary key
                    '*_check' => 'c', // check constraint
                ],
            ],
        ]);

        // Create table
        Schema::create('test_constraints', static function (Blueprint $table): void {
            $table->id();
            $table->integer('age');
            // Primary key is created automatically
            // No check constraint
        });

        // Add check constraint manually
        DB::statement('ALTER TABLE test_constraints ADD CONSTRAINT test_constraints_age_check CHECK (age >= 0)');

        // Validate
        $result = PgHelperLibrary::validateStructure(['test_constraints']);

        // Should be valid now
        self::assertTrue($result['valid']);
        self::assertEmpty($result['errors']);

        // Clean up
        Schema::dropIfExists('test_constraints');
    }

    public function test_sequence_warning_detection(): void
    {
        // Create table and insert with explicit ID
        Schema::create('test_seq_warning', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        // Insert with explicit ID that's higher than sequence
        DB::table('test_seq_warning')->insert([
            'id' => 1000,
            'name' => 'Test',
        ]);

        // Don't fix the sequence intentionally

        // Validate
        $result = PgHelperLibrary::validateStructure(['test_seq_warning']);

        // Should have warning about sequence
        self::assertArrayHasKey('test_seq_warning', $result['warnings']);
        $warnings = $result['warnings']['test_seq_warning'];

        $hasSeqWarning = false;
        foreach ($warnings as $warning) {
            if (str_contains($warning, 'Sequence') && str_contains($warning, 'may need to be reset')) {
                $hasSeqWarning = true;

                break;
            }
        }
        self::assertTrue($hasSeqWarning, 'Should have warning about sequence needing reset');

        // Clean up
        Schema::dropIfExists('test_seq_warning');
    }
}
