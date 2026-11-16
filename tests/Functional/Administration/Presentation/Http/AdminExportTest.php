<?php

declare(strict_types=1);

namespace Tests\Functional\Administration\Presentation\Http;

use ReflectionProperty;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

function adminExportContent(Response $response): string
{
    if (!$response instanceof StreamedResponse) {
        return (string) $response->getContent();
    }

    $callback = (new ReflectionProperty(StreamedResponse::class, 'callback'))->getValue($response);

    if (!is_callable($callback)) {
        return '';
    }

    ob_start();
    $callback();

    return (string) ob_get_clean();
}

it('exports accounts as a CSV without sensitive profile data', function () {
    authTestCreateAdmin($this, 'admin-export-users@example.com');
    authTestCreateUser($this, 'user-export-users@example.com');
    $token = groupTestAccessToken($this, 'admin-export-users@example.com');

    $response = $this->api->get('app_admin_user_export', server: groupTestServer($token));

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('contas.csv');

    $content = adminExportContent($response);
    $lines = array_values(array_filter(explode("\n", trim($content))));

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($lines[0])->toContain('id;nome;email;email_verificado;situacao;papel;nivel;onboarding;criado_em')
        ->and($content)->toContain('user-export-users@example.com')
        ->and($content)->not->toContain('cpf')
        ->and($content)->not->toContain('telefone');
});

it('applies the list filters to the account export', function () {
    authTestCreateAdmin($this, 'admin-export-filter@example.com');
    authTestCreateUser($this, 'target-export-filter@example.com');
    $token = groupTestAccessToken($this, 'admin-export-filter@example.com');

    $response = $this->api->get(
        'app_admin_user_export',
        ['status' => 'SUSPENDED'],
        server: groupTestServer($token),
    );

    $content = adminExportContent($response);
    $lines = array_values(array_filter(explode("\n", trim($content))));

    expect($lines)->toHaveCount(1)
        ->and($content)->not->toContain('target-export-filter@example.com');
});

it('exports groups and activities as CSV', function () {
    $context = activityTestGroupWithMembers($this, 'admin-export-groups');
    activityTestCreate($this, $context['adminToken'], $context['groupId'], ['title' => 'Treino Exportado']);

    authTestCreateAdmin($this, 'admin-export-groups-sigame@example.com');
    $token = groupTestAccessToken($this, 'admin-export-groups-sigame@example.com');
    $server = groupTestServer($token);

    $groups = adminExportContent($this->api->get('app_admin_group_export', server: $server));
    expect($groups)->toContain('Corrida no Parque')
        ->and($groups)->toContain('responsavel_nome');

    $activities = adminExportContent($this->api->get('app_admin_activity_export', server: $server));
    expect($activities)->toContain('Treino Exportado')
        ->and($activities)->toContain('grupo_nome')
        ->and($activities)->toContain('presencas_validadas');
});

it('does not allow a regular user to export data', function () {
    groupTestCreateUser($this, 'regular-export@example.com');
    $token = groupTestAccessToken($this, 'regular-export@example.com');

    foreach (['app_admin_user_export', 'app_admin_group_export', 'app_admin_activity_export'] as $route) {
        $response = $this->api->get($route, server: groupTestServer($token));
        expect($response->getStatusCode())->toBe(403);
    }
});
