<?php

namespace HaakCo\PostgresHelper\Facades;

use Illuminate\Support\Facades\Facade;

class PostgresHelper extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'postgreshelper';
    }
}
