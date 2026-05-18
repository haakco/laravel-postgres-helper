<?php

declare(strict_types=1);

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recreates helper functions so fixAll() ignores non-public extension schemas
     * and never resets sequences below PostgreSQL's valid lower bound.
     */
    public function up(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $eventTriggersWereEnabled = PgHelperLibrary::areEventTriggersEnabled();

        DB::statement("COMMENT ON FUNCTION public.fix_db() IS 'Updated by laravel-postgres-helper v4.0.10 on {$timestamp} - exclude internal schemas and guard sequence lower bound'");

        PgHelperLibrary::updateDateColumnsDefault();
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
        PgHelperLibrary::addAutoApplyStandards();

        if ($eventTriggersWereEnabled) {
            PgHelperLibrary::enableEventTriggers();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Functions use CREATE OR REPLACE. Previous versions would need to be restored manually.
    }
};
