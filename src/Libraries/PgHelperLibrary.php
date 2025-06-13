<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries;

use Illuminate\Support\Facades\DB;

class PgHelperLibrary
{
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
        $sql = 'select public.fix_db()';
        DB::update($sql);
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
}
