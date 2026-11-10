<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * @return array<string, string>
 */
function gamificationRulesTestServer(FunctionalTestCase $test, string $email = 'rules@example.com'): array
{
    authTestCreateUser($test, $email);
    $token = authTestAccessToken(authTestLogin($test, $email));

    return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
}

it('returns the gamification rules catalog', function () {
    $server = gamificationRulesTestServer($this);

    $response = $this->api->get('app_gamification_rules', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];

    expect($data['ruleVersion'])->toBe('2026-11-v1')
        ->and($data['timezone'])->toBe('America/Sao_Paulo')
        ->and($data['levels']['minLevel'])->toBe(1)
        ->and($data['levels']['maxLevel'])->toBe(10)
        ->and($data['levels']['curve'])->toHaveCount(10)
        ->and($data['levels']['curve'][0])->toBe(['level' => 1, 'thresholdXp' => 0, 'xpForNextLevel' => 100])
        ->and($data['levels']['curve'][4])->toBe(['level' => 5, 'thresholdXp' => 1000, 'xpForNextLevel' => 500])
        ->and($data['levels']['curve'][9])->toBe(['level' => 10, 'thresholdXp' => 4500, 'xpForNextLevel' => null])
        ->and($data['xpSources'])->toHaveCount(4)
        ->and(array_column($data['xpSources'], 'kind'))->toBe(['onboarding', 'activity', 'weekly_start', 'weekly_goal'])
        ->and($data['activity']['eligibility']['minimumCheckIns'])->toBe(2)
        ->and($data['activity']['maxXpPerActivity'])->toBe(100)
        ->and($data['activity']['weekendMultiplierPercent'])->toBe(120)
        ->and($data['activity']['baseXpByModality'])->toBe([
            ['modality' => 'caminhada', 'xp' => 20],
            ['modality' => 'corrida', 'xp' => 25],
            ['modality' => 'ciclismo', 'xp' => 30],
        ])
        ->and($data['activity']['dailyFactorBands'][1])->toBe(['minActivities' => 4, 'maxActivities' => 4, 'percent' => 25])
        ->and($data['weekly']['goalActivities'])->toBe(2)
        ->and($data['weekly']['goalBonusByStreak'])->toHaveCount(7)
        ->and($data['weekly']['goalBonusByStreak'][0])->toBe(['streak' => 1, 'amount' => 20])
        ->and($data['weekly']['goalBonusByStreak'][6])->toBe(['streak' => 7, 'amount' => 50])
        ->and($data['weekly']['goalBonusCapFromStreak'])->toBe(7)
        ->and($data['decay']['graceDays'])->toBe(7)
        ->and($data['decay']['periodDays'])->toBe(2)
        ->and($data['decay']['floorLevel'])->toBe(2)
        ->and($data['streakStates'])->toHaveCount(4)
        ->and($data['streakStates'][0])->toBe([
            'value' => 'APAGADO',
            'label' => 'Foguinho apagado',
            'description' => 'O usuário nunca teve uma semana válida ou a última semana terminou sem atividades.',
        ]);
});

it('revalidates the rules catalog with an etag', function () {
    $server = gamificationRulesTestServer($this, 'rules-etag@example.com');

    $first = $this->api->get('app_gamification_rules', server: $server);

    expect($first->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($first->headers->get('ETag'))->toBe('"2026-11-v1"')
        ->and($first->headers->get('Cache-Control'))->toContain('private')
        ->and($first->headers->get('Cache-Control'))->toContain('max-age=3600');

    $second = $this->api->get(
        'app_gamification_rules',
        server: $server + ['HTTP_IF_NONE_MATCH' => '"2026-11-v1"'],
    );

    expect($second->getStatusCode())->toBe(Response::HTTP_NOT_MODIFIED)
        ->and($second->getContent())->toBe('');
});

it('requires authentication for the rules catalog', function () {
    expect($this->api->get('app_gamification_rules')->getStatusCode())
        ->toBe(Response::HTTP_UNAUTHORIZED);
});
