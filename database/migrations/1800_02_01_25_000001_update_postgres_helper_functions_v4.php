<?php

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration ensures that users upgrading from v3.x to v4.x get the latest
     * PostgreSQL function definitions, even if they've already run the original migrations.
     */
    public function up(): void
    {
        // Add a comment to track this update
        $timestamp = now()->format('Y-m-d H:i:s');
        DB::statement("COMMENT ON FUNCTION public.fix_db() IS 'Updated by laravel-postgres-helper v4.0.0 on {$timestamp}'");
        
        // Re-create all core functions to ensure they have the latest definitions
        // These use CREATE OR REPLACE, so they're safe to run multiple times
        PgHelperLibrary::updateDateColumnsDefault();
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
        
        // Log the update for debugging purposes
        if (function_exists('logger')) {
            logger()->info('PostgreSQL helper functions updated to v4.0.0');
        }
    }

    /**
     * Reverse the migrations.
     *
     * We don't rollback function updates as they might break existing functionality.
     * The functions will remain at their updated versions.
     */
    public function down(): void
    {
        // Intentionally left empty - we don't downgrade PostgreSQL functions
        // as it could break existing code that depends on bug fixes or improvements
        
        if (function_exists('logger')) {
            logger()->info('PostgreSQL helper functions rollback skipped - functions remain at v4.0.0');
        }
    }
};