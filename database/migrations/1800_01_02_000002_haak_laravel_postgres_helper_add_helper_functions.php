<?php

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    public function up()
    {
        PgHelperLibrary::updateDateColumnsDefault();
        PgHelperLibrary::addUpdateUpdatedAtColumn();
        PgHelperLibrary::addUpdateUpdatedAtColumnForTables();
        PgHelperLibrary::addFixAllSeq();
        PgHelperLibrary::addFixDb();
        PgHelperLibrary::fixAll();
    }

    public function down()
    {
    }
};
