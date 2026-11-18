<?php

namespace JeffersonGoncalves\QuebecPia\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use JeffersonGoncalves\Quebec\Attributes\PersonalData;
use JeffersonGoncalves\Quebec\Concerns\InteractsWithPersonalData;
use JeffersonGoncalves\Quebec\Contracts\HasPersonalData;

#[PersonalData('email', retention: '5 years')]
#[PersonalData('tax_id')]
class Customer extends Model implements HasPersonalData
{
    use InteractsWithPersonalData;

    protected $table = 'customers';
}
