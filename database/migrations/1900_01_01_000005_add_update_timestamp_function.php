<?php

use App\Libraries\Helper\PgHelperLibrary;
use Illuminate\Database\Migrations\Migration;

class AddUpdateTimestampFunction extends Migration
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
