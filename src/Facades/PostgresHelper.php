<?php

declare(strict_types=1);

namespace HaakCo\PostgresHelper\Facades;

use Illuminate\Support\Facades\Facade;

class PostgresHelper extends Facade
{
    /**
     * Get the registered name of the component.
     */
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return 'postgreshelper';
    }
}
