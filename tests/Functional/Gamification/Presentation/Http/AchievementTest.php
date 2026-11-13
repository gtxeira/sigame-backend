<?php

declare(strict_types=1);

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array{server: array<string, string>, userId: string, token: string}
 */
function achievementTestContext(
    FunctionalTestCase $test,
    string $email = 'achievement@example.com',
): array {
    $user = authTestCreateUser($test, $email);
    $token = authTestAccessToken(authTestLogin($test, $email));

    return [
        'server' => ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        'userId' => $user->id()->value(),
        'token' => $token,
    ];
}

function achievementTestUnlock(FunctionalTestCase $test, string $userId): void
{
    /** @var UserAchievementRepositoryInterface $repository */
    /* @phpstan-ignore-next-line */
    $repository = $test->api->getKernelBrowser()->getContainer()->get(UserAchievementRepositoryInterface::class);

    $repository->save(UserAchievement::unlock(
        PlayerId::fromString($userId),
        AchievementCode::LENDA,
    ));
}

it('lists only the unlocked achievements of the authenticated user', function () {
    $context = achievementTestContext($this);

    $empty = $this->api->get('app_my_achievements', server: $context['server']);
    expect($empty->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($empty)['data'])->toBe([]);

    achievementTestUnlock($this, $context['userId']);

    $response = $this->api->get('app_my_achievements', server: $context['server']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe('LENDA')
        ->and($data[0]['name'])->toBe('Lenda')
        ->and($data[0]['description'])->toBe('Alcance o nível 10.')
        ->and($data[0]['achievedAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T/')
        ->and($data[0]['celebratedAt'])->toBeNull();
});

it('celebrates an achievement idempotently', function () {
    $context = achievementTestContext($this, 'achievement-celebrate@example.com');
    achievementTestUnlock($this, $context['userId']);

    $first = $this->api->post(
        'app_my_achievement_celebrated',
        [],
        ['code' => 'LENDA'],
        $context['server'],
    );

    expect($first->getStatusCode())->toBe(Response::HTTP_OK);

    $firstData = authTestBody($first)['data'];
    expect($firstData['celebratedAt'])->not->toBeNull();

    $second = $this->api->post(
        'app_my_achievement_celebrated',
        [],
        ['code' => 'LENDA'],
        $context['server'],
    );

    expect($second->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($second)['data']['celebratedAt'])->toBe($firstData['celebratedAt']);

    $list = $this->api->get('app_my_achievements', server: $context['server']);
    expect(authTestBody($list)['data'][0]['celebratedAt'])->toBe($firstData['celebratedAt']);
});

it('rejects celebrating an unknown achievement', function () {
    $context = achievementTestContext($this, 'achievement-unknown@example.com');

    $response = $this->api->post(
        'app_my_achievement_celebrated',
        [],
        ['code' => 'NAO_EXISTE'],
        $context['server'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($this->api->errorCodes())->toContain('ACHIEVEMENT_NOT_FOUND');
});

it('does not let a user celebrate another user achievement', function () {
    $owner = achievementTestContext($this, 'achievement-owner@example.com');
    $other = achievementTestContext($this, 'achievement-other@example.com');
    achievementTestUnlock($this, $owner['userId']);

    $response = $this->api->post(
        'app_my_achievement_celebrated',
        [],
        ['code' => 'LENDA'],
        $other['server'],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND)
        ->and($this->api->errorCodes())->toContain('ACHIEVEMENT_NOT_FOUND');
});

it('requires authentication for achievement endpoints', function () {
    expect($this->api->get('app_my_achievements')->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->post('app_my_achievement_celebrated', [], ['code' => 'LENDA'])->getStatusCode())
        ->toBe(Response::HTTP_UNAUTHORIZED);
});
