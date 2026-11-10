<?php

declare(strict_types=1);

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\OnboardingCompleted;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Modality;
use Tests\FunctionalTestCase;

function groupTestCreateUser(FunctionalTestCase $test, string $email, int $level = 2): User
{
    $user = authTestCreateUser($test, $email);

    if ($level >= 2) {
        $user->completeOnboarding(
            modalities: [Modality::RUNNING],
            primaryModality: null,
        );
        authTestUserRepository($test)->save($user);

        /* @phpstan-ignore-next-line */
        $test->api->getKernelBrowser()->getContainer()
            ->get(EventDispatcherInterface::class)
            ->dispatch(OnboardingCompleted::now($user->id()->value()));
    }

    return $user;
}

function groupTestAccessToken(FunctionalTestCase $test, string $email): string
{
    return authTestAccessToken(authTestLogin($test, $email));
}

/**
 * @return array<string, string>
 */
function groupTestServer(string $accessToken): array
{
    return ['HTTP_AUTHORIZATION' => 'Bearer '.$accessToken];
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function groupTestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Corrida no Parque',
        'description' => 'Encontros aos sábados pela manhã.',
        'modality' => Modality::RUNNING->value,
        'communicationLink' => 'https://chat.example.com/grupo/corrida',
    ], $overrides);
}

/**
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function groupTestCreate(FunctionalTestCase $test, string $accessToken, array $overrides = []): array
{
    $response = $test->api->post(
        'app_group_create',
        groupTestPayload($overrides),
        server: groupTestServer($accessToken),
    );

    return authTestBody($response)['data'];
}

function groupTestRequestMembership(FunctionalTestCase $test, string $accessToken, string $groupId): string
{
    $response = $test->api->post(
        'app_group_membership_request',
        [],
        ['id' => $groupId],
        groupTestServer($accessToken),
    );

    return (string) authTestBody($response)['data']['id'];
}
