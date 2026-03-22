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
     * Fixes identifier quoting in PostgreSQL helper functions to handle
     * mixed-case table names and exclude foreign tables (FDW).
     */
    public function up(): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        DB::statement("COMMENT ON FUNCTION public.fix_db() IS 'Updated by laravel-postgres-helper v4.0.9 on {$timestamp} - fix identifier quoting'");

        PgHelperLibrary::updateDateColumnsDefault();
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Functions use CREATE OR REPLACE - previous versions would need to be restored manually
    }
};
