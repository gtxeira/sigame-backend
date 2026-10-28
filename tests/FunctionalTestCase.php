<?php

declare(strict_types=1);

namespace Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Helpers\ApiClient;

/**
 * @property ApiClient $api
 */
abstract class FunctionalTestCase extends WebTestCase
{
}
