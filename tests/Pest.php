<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you receive receives the test case as `$this`. This allows
| you to extend the base test case: `Tests\TestCase` for unit tests and
| `Tests\FunctionalTestCase` (extending Symfony's WebTestCase) for
| functional tests. You can also add traits or use helper functions
| here.
|
*/

use Faker\Generator;
use Faker\Provider\pt_BR\Company;
use Faker\Provider\pt_BR\Person;
use Faker\Provider\pt_BR\PhoneNumber;
use Symfony\Component\Serializer\SerializerInterface;
use Tests\FunctionalTestCase;
use Tests\Helpers\ApiClient;
use Tests\IntegrationTestCase;
use Tests\TestCase;

use function Zenstruck\Foundry\faker as foundryFaker;

require_once __DIR__.'/Functional/Identity/Presentation/Http/AuthHelpers.php';
require_once __DIR__.'/Functional/Groups/Presentation/Http/GroupHelpers.php';
require_once __DIR__.'/Functional/Activities/Presentation/Http/ActivityHelpers.php';
require_once __DIR__.'/Helpers/LedgerHelpers.php';
require_once __DIR__.'/Helpers/OpenApiHelpers.php';

pest()
    ->extend(TestCase::class)
    ->group('unit')
    ->in('Unit');

pest()
    ->extend(IntegrationTestCase::class)
    ->group('integration')
    ->in('Integration');

pest()
    ->extend(FunctionalTestCase::class)
    ->beforeEach(function () {
        $client = $this->createClient();
        $client->disableReboot();

        $container = $client->getContainer();

        /** @phpstan-ignore-next-line */
        $serializer = $container->get(SerializerInterface::class);

        $router = $container->get('router');

        $container->get('cache.rate_limiter')->clear();

        $this->api = new ApiClient(
            client: $client,
            serializer: $serializer,
            router: $router,
        );
    })
    ->group('functional')
    ->in('Functional');

/**
 * Helper global para acessar o Faker do Zenstruck Foundry tipado em pt_BR.
 */
function faker(): Generator|Person|Company|PhoneNumber
{
    /**
     * @var Generator|Person|Company|PhoneNumber $faker
     *
     * @phpstan-ignore-next-line
     */
    $faker = foundryFaker();

    return $faker;
}
