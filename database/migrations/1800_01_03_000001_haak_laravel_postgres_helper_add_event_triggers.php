<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the auto-apply standards function and helper
        $sql = file_get_contents(__DIR__ . '/../../src/Libraries/sql/000060_auto_apply_standards.sql');
        if (false === $sql) {
            throw new RuntimeException('Failed to read SQL file: 000060_auto_apply_standards.sql');
        }
        DB::unprepared($sql);
        
        // Note: The event trigger is not created by default
        // It must be explicitly enabled using PgHelperLibrary::enableEventTriggers()
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop event trigger if it exists
        DB::unprepared("
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_event_trigger 
                    WHERE evtname = 'auto_apply_standards_trigger'
                ) THEN
                    DROP EVENT TRIGGER auto_apply_standards_trigger;
                END IF;
            END $$;
        ");
        
        // Drop functions
        DB::unprepared('DROP FUNCTION IF EXISTS auto_apply_table_standards() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS fix_sequence_for_table(text) CASCADE');
    }
};