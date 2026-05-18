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
        $sql = "SELECT c.table_name
                FROM information_schema.columns c
                JOIN information_schema.tables t
                  ON c.table_name = t.table_name
                  AND c.table_schema = t.table_schema
                WHERE c.column_name = ?
                  AND c.table_schema = 'public'
                  AND t.table_type = 'BASE TABLE'";
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
     * @return array<object{sequence_schema: string, sequence_name: string, table_schema: string, table_name: string, column_name: string}>
     */
    protected static function getTableSequences(string $tableName): array
    {
        return DB::select("
            SELECT
                seq_ns.nspname AS sequence_schema,
                seq.relname AS sequence_name,
                tbl_ns.nspname AS table_schema,
                tbl.relname AS table_name,
                att.attname AS column_name
            FROM pg_class seq
            JOIN pg_namespace seq_ns ON seq.relnamespace = seq_ns.oid
            JOIN pg_depend dep ON seq.oid = dep.objid
            JOIN pg_class tbl ON dep.refobjid = tbl.oid
            JOIN pg_namespace tbl_ns ON tbl.relnamespace = tbl_ns.oid
            JOIN pg_attribute att ON att.attrelid = tbl.oid AND att.attnum = dep.refobjsubid
            WHERE seq.relkind = 'S'
            AND seq_ns.nspname = 'public'
            AND tbl_ns.nspname = 'public'
            AND tbl.relname = ?
        ", [$tableName]);
    }

    /**
     * Get column info for a sequence.
     *
     * @return object{sequence_schema: string, column_name: string}|null
     */
    protected static function getSequenceColumnInfo(string $tableName, string $sequenceName): ?object
    {
        return DB::selectOne("
            SELECT seq_ns.nspname AS sequence_schema, pg_attribute.attname AS column_name
            FROM pg_attribute
            JOIN pg_class ON pg_attribute.attrelid = pg_class.oid
            JOIN pg_namespace tbl_ns ON pg_class.relnamespace = tbl_ns.oid
            JOIN pg_depend ON pg_depend.refobjid = pg_class.oid
            JOIN pg_class seq ON seq.oid = pg_depend.objid
            JOIN pg_namespace seq_ns ON seq.relnamespace = seq_ns.oid
            WHERE pg_class.relname = ?
            AND tbl_ns.nspname = 'public'
            AND seq.relname = ?
            AND seq_ns.nspname = 'public'
            AND pg_attribute.attnum = pg_depend.refobjsubid
        ", [$tableName, $sequenceName]);
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
