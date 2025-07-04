<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable UUID extension for PostgreSQL
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't drop the extension as other tables might depend on it
        // DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
    }
};
