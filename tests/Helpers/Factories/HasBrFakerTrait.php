<?php

declare(strict_types=1);

namespace Tests\Helpers\Factories;

use Faker\Generator;
use Faker\Provider\pt_BR\Company;
use Faker\Provider\pt_BR\Person;
use Faker\Provider\pt_BR\PhoneNumber;

/**
 * Informa à IDE que qualquer classe que usar essa Trait terá o método `faker()` tipado para pt_BR.
 *
 * @method static Generator|Person|Company|PhoneNumber faker()
 */
trait HasBrFakerTrait
{
}
