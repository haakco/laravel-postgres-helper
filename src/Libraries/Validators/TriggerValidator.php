<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Libraries\Validators;

use Illuminate\Support\Facades\DB;

class TriggerValidator
{
    /**
     * Check if table has required triggers.
     *
     * @return array<string> Warning messages
     */
    public static function validateUpdatedAtTrigger(string $tableName, bool $hasUpdatedAtColumn): array
    {
        if (!$hasUpdatedAtColumn) {
            return [];
        }

        return self::triggerExists($tableName)
            ? []
            : ["Table has 'updated_at' column but missing update trigger"];
    }

    /**
     * Check if the updated_at trigger exists for a table.
     */
    private static function triggerExists(string $tableName): bool
    {
        $triggerName = "update_{$tableName}_updated_at";

        $result = DB::selectOne(
            'SELECT 1 FROM information_schema.triggers
             WHERE trigger_name = ? AND event_object_table = ?',
            [$triggerName, $tableName]
        );

        return null !== $result;
    }
}
