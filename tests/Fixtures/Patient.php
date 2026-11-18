<?php

namespace JeffersonGoncalves\QuebecPia\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\Quebec\Attributes\PersonalData;
use JeffersonGoncalves\Quebec\Concerns\InteractsWithPersonalData;
use JeffersonGoncalves\Quebec\Contracts\HasPersonalData;
use JeffersonGoncalves\Quebec\Enums\DataCategory;

#[PersonalData('name')]
#[PersonalData('diagnosis', category: DataCategory::Sensitive)]
class Patient extends Model implements HasPersonalData
{
    use InteractsWithPersonalData;

    protected $table = 'patients';
}
