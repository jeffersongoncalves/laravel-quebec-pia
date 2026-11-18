<?php

namespace JeffersonGoncalves\QuebecPia\Facades;

use Illuminate\Support\Facades\Facade;
use JeffersonGoncalves\QuebecPia\PiaManager;

/**
 * @method static list<array{activity: \JeffersonGoncalves\QuebecGovernance\Models\ProcessingActivity, reasons: list<string>}> candidates()
 * @method static array<string, mixed> export(\JeffersonGoncalves\QuebecPia\Models\ImpactAssessment $assessment)
 *
 * @see PiaManager
 */
class QuebecPia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PiaManager::class;
    }
}
