<?php

namespace JeffersonGoncalves\QuebecPia\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\QuebecPia\QuebecPia
 */
class QuebecPia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-quebec-pia';
    }
}
