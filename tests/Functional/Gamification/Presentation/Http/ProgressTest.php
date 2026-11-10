<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;

/**
 * @return array{token: string, server: array<string, string>}
 */
function gamificationTestOnboardedUser(
    \Tests\FunctionalTestCase $test,
    string $email = 'progress@example.com',
): array {
    authTestCreateUser($test, $email);
    $token = authTestAccessToken(authTestLogin($test, $email));
    $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$token];

    $onboarding = $test->api->post(
        'app_me_onboarding',
        ['modalities' => ['corrida'], 'primaryModality' => 'corrida'],
        server: $server,
    );

    expect($onboarding->getStatusCode())->toBe(Response::HTTP_OK);

    return ['token' => $token, 'server' => $server];
}

it('returns the progress after onboarding', function () {
    $context = gamificationTestOnboardedUser($this);

    $response = $this->api->get('app_my_progress', server: $context['server']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];

    expect($data['level'])->toBe(2)
        ->and($data['xpBalance'])->toBe(100)
        ->and($data['xpLifetime'])->toBe(100)
        ->and($data['levelStartXp'])->toBe(100)
        ->and($data['nextLevelXp'])->toBe(300)
        ->and($data['xpToNextLevel'])->toBe(200)
        ->and($data['barPercent'])->toBe(0)
        ->and($data['currentWeeklyStreak'])->toBe(0)
        ->and($data['bestWeeklyStreak'])->toBe(0)
        ->and($data['weeklyGoal'])->toBe(2)
        ->and($data['weeklyActivities'])->toBe(0)
        ->and($data['streakState'])->toBe('APAGADO')
        ->and($data['ruleVersion'])->toBe('2026-11-v1')
        ->and($data['weekStart'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
        ->and($data['weekEnd'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('lists the xp history', function () {
    $context = gamificationTestOnboardedUser($this, 'progress-history@example.com');

    $response = $this->api->get('app_my_xp_history', server: $context['server']);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $body = authTestBody($response);
    $data = $body['data'];

    expect($data)->toHaveCount(1)
        ->and($data[0]['kind'])->toBe('onboarding')
        ->and($data[0]['amount'])->toBe(100)
        ->and($data[0]['description'])->toBe('Onboarding concluído')
        ->and($body['meta']['pagination']['page'])->toBe(1)
        ->and($body['meta']['pagination']['perPage'])->toBe(20)
        ->and($body['meta']['pagination']['total'])->toBe(1)
        ->and($body['meta']['pagination']['totalPages'])->toBe(1);
});

it('requires authentication for progress endpoints', function () {
    expect($this->api->get('app_my_progress')->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->get('app_my_xp_history')->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
});
