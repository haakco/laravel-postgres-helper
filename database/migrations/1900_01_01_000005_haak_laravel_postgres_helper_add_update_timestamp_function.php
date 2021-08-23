<?php

use HaakCo\PostgresHelper\Libraries\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;

class HaakLaravelPostgresHelperAddUpdateTimestampFunction extends Migration
{

    public function up()
    {
        PgHelperLibrary::addUpdatedAtFunction();
    }

    public function down()
    {
        PgHelperLibrary::removeUpdatedAtFunction();
    }
}
