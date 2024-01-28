<?php

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    public function up()
    {
        PgHelperLibrary::updateDateColumnsDefault();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
        PgHelperLibrary::fixAll();
        PgHelperLibrary::removeUpdatedAtFunction();
    }

    public function down()
    {
    }
};
