<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds commonly used UUID helper functions that many projects expect.
     */
    public function up(): void
    {
        // Create a trigger function that generates a UUID if the id is null
        DB::statement('
            CREATE OR REPLACE FUNCTION generate_uuid_if_null()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.id IS NULL THEN
                    NEW.id = uuid_generate_v4();
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Add a comment to document the function
        DB::statement("
            COMMENT ON FUNCTION generate_uuid_if_null() IS 
            'Trigger function that generates a UUID v4 for the id column if it is null. Added by laravel-postgres-helper v4.0.0';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS generate_uuid_if_null() CASCADE');
    }
};