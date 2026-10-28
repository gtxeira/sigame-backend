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
use Tests\TestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\faker as foundryFaker;

pest()
    ->extend(TestCase::class)
    ->group('unit')
    ->in('Unit');

pest()
    ->extend(FunctionalTestCase::class)
    ->use(ResetDatabase::class)
    ->use(Factories::class)
    ->beforeEach(function () {
        $client = $this->createClient();

        $container = $client->getContainer();

        /** @phpstan-ignore-next-line */
        $serializer = $container->get(SerializerInterface::class);

        $router = $container->get('router');
        $serverName = $_ENV['SERVER_NAME'] ?? $_SERVER['SERVER_NAME'];

        $this->api = new ApiClient(
            client: $client,
            serializer: $serializer,
            router: $router,
            apiDomain: $serverName,
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
