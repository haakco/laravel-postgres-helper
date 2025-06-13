<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Traits;

use Illuminate\Support\Facades\DB;

trait PostgresQueries
{
    /**
     * Get all user tables in the public schema.
     *
     * @return array<string>
     */
    protected static function getUserTables(): array
    {
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        return array_map(static fn ($table) => $table->tablename, $tables);
    }

    /**
     * Get all tables that have a specific column.
     *
     * @return array<string>
     */
    protected static function getTablesWithColumn(string $columnName): array
    {
        $sql = "SELECT table_name FROM information_schema.columns
                WHERE column_name = ? AND table_schema = 'public'";
        $tables = DB::select($sql, [$columnName]);

        return array_map(static fn ($table) => $table->table_name, $tables);
    }

    /**
     * Check if a trigger exists for a table.
     */
    protected static function triggerExists(string $triggerName, string $tableName): bool
    {
        $result = DB::selectOne(
            "SELECT 1 FROM information_schema.triggers
             WHERE trigger_name = ? AND event_object_table = ? AND trigger_schema = 'public'",
            [$triggerName, $tableName]
        );

        return null !== $result;
    }

    /**
     * Get table columns with their properties.
     *
     * @return array<object{column_name: string, data_type: string, is_nullable: string}>
     */
    protected static function getTableColumns(string $tableName): array
    {
        return DB::select(
            "SELECT column_name, data_type, is_nullable
             FROM information_schema.columns
             WHERE table_name = ? AND table_schema = 'public'",
            [$tableName]
        );
    }

    /**
     * Get table indexes.
     *
     * @return array<object{indexname: string}>
     */
    protected static function getTableIndexes(string $tableName): array
    {
        return DB::select(
            "SELECT indexname FROM pg_indexes
             WHERE tablename = ? AND schemaname = 'public'",
            [$tableName]
        );
    }

    /**
     * Get sequences for a specific table.
     *
     * @return array<object{sequence_name: string}>
     */
    protected static function getTableSequences(string $tableName): array
    {
        return DB::select("
            SELECT seq.relname AS sequence_name
            FROM pg_class seq
            JOIN pg_depend dep ON seq.oid = dep.objid
            JOIN pg_class tbl ON dep.refobjid = tbl.oid
            WHERE seq.relkind = 'S'
            AND tbl.relname = ?
        ", [$tableName]);
    }

    /**
     * Get column info for a sequence.
     *
     * @return object{column_name: string}|null
     */
    protected static function getSequenceColumnInfo(string $tableName, string $sequenceName): ?object
    {
        return DB::selectOne('
            SELECT attname AS column_name
            FROM pg_attribute
            JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
            JOIN pg_depend ON pg_depend.refobjid = pg_class.oid
            JOIN pg_class seq ON seq.oid = pg_depend.objid
            WHERE pg_class.relname = ?
            AND seq.relname = ?
            AND pg_attribute.attnum = pg_depend.refobjsubid
        ', [$tableName, $sequenceName]);
    }

    /**
     * Check if a column exists in a table.
     */
    protected static function columnExists(string $tableName, string $columnName): bool
    {
        $result = DB::selectOne("
            SELECT 1 FROM information_schema.columns
            WHERE table_name = ?
            AND column_name = ?
            AND table_schema = 'public'
        ", [$tableName, $columnName]);

        return null !== $result;
    }

    /**
     * Get constraints for a table.
     *
     * @return array<object{conname: string, contype: string}>
     */
    protected static function getTableConstraints(string $tableName): array
    {
        return DB::select('
            SELECT conname, contype
            FROM pg_constraint
            WHERE conrelid = ?::regclass
        ', [$tableName]);
    }
}
