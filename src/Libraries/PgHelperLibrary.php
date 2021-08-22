<?php

namespace App\Libraries\Helper;

use Illuminate\Support\Facades\DB;

class PgHelperLibrary
{
    public static function removeUpdatedAtFunction(): void
    {
        DB::statement(
        /**
         * @lang PostgreSQL
         */
            'DROP FUNCTION IF EXISTS update_updated_at_column'
        );
    }

    public static function addUpdatedAtFunction(): void
    {
        DB::statement(
        /**
         * @lang PostgreSQL
         */
            "CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
      NEW.updated_at = now();
      RETURN NEW;
END;
$$ language 'plpgsql'"
        );
    }

    /**
     *
     */
    public static function addMissingUpdatedAtTriggers(): void
    {
        $sql = "SELECT
    t.table_schema || '.' || t.table_name as name
FROM
    information_schema.tables t
        JOIN information_schema.columns c
             ON t.table_name = c.table_name
                 AND t.table_schema = c.table_schema
                 AND c.column_name = 'updated_at'
        LEFT JOIN information_schema.triggers tr
                  ON t.table_schema = tr.trigger_schema
                      AND t.table_name = tr.event_object_table
                      AND tr.action_statement = 'EXECUTE FUNCTION update_updated_at_column()'
WHERE
    tr.event_object_table IS NULL
ORDER BY
    t.table_schema,
    t.table_name";
        $tables = DB::select($sql);
        foreach ($tables as $table) {
            self::setUpdatedAtTrigger($table->name);
        }
    }

    public static function setUpdatedAtTrigger($tableName): void
    {
        $tableNameParts = explode('.', $tableName);
        $name = $tableNameParts[1] ?? $tableName;

        DB::statement(
        /**
         * @lang PostgreSQL
         */
            "DROP TRIGGER IF EXISTS ${name}_before_update_updated_at ON ${tableName}"
        );
        DB::statement(
        /**
         * @lang PostgreSQL
         */
            "CREATE TRIGGER
            ${name}_before_update_updated_at BEFORE UPDATE ON ${tableName}
            FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column()"
        );
    }

    /** @noinspection UnknownInspectionInspection */

    public static function setSequenceStart(string $tableName, ?int $startNo = null): void
    {
        if ($startNo === null) {
            /** @noinspection SqlResolve */
            $newIdResult = DB::selectOne(
                "SELECT
  COALESCE(MAX(t.id) + 1, 1) AS next_id
FROM
  ${tableName} t"
            );

            $startNo = $newIdResult->next_id;
        }
        DB::insert(
            "SELECT setval('${tableName}_id_seq',
              ${startNo},
              TRUE);"
        );
    }
}
