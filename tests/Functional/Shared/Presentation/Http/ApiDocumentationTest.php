<?php

declare(strict_types=1);

use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraint;
use Tests\FunctionalTestCase;

/**
 * Recupera um valor aninhado do documento OpenAPI, falhando com uma
 * mensagem explícita quando o caminho não existe.
 */
function apiDocValue(mixed $data, string|int ...$path): mixed
{
    foreach ($path as $key) {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            throw new RuntimeException(sprintf('Caminho "%s" ausente na documentação OpenAPI.', implode('.', $path)));
        }

        $data = $data[$key];
    }

    return $data;
}

/**
 * @return array<string, mixed>
 */
function readApiDoc(FunctionalTestCase $test): array
{
    $response = $test->api->get('app.api_doc_json');

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    /** @var array<string, mixed> $doc */
    $doc = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    return $doc;
}

it('declares the bearerAuth security scheme', function () {
    $doc = readApiDoc($this);

    expect(apiDocValue($doc, 'components', 'securitySchemes', 'bearerAuth'))
        ->toMatchArray([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);
});

it('groups every documented tag', function () {
    $doc = readApiDoc($this);

    $tags = array_column(apiDocValue($doc, 'tags'), 'name');
    $groups = apiDocValue($doc, 'x-tagGroups');

    $groupedTags = [];

    foreach ($groups as $group) {
        expect($group)->toHaveKeys(['name', 'tags']);
        $groupedTags = array_merge($groupedTags, $group['tags']);
    }

    expect(array_values(array_unique($groupedTags)))->toEqualCanonicalizing($tags)
        ->and(array_diff($groupedTags, $tags))->toBe([]);
});

it('documents a summary and description for every operation', function () {
    $doc = readApiDoc($this);

    $missing = [];

    foreach (apiDocValue($doc, 'paths') as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if ('' === trim((string) ($operation['summary'] ?? ''))) {
                $missing[] = sprintf('%s %s: summary', strtoupper((string) $method), (string) $path);
            }

            if ('' === trim((string) ($operation['description'] ?? ''))) {
                $missing[] = sprintf('%s %s: description', strtoupper((string) $method), (string) $path);
            }
        }
    }

    expect($missing)->toBe([]);
});

it('follows the summary writing convention', function () {
    $doc = readApiDoc($this);

    $approvedVerbs = [
        'Listar',
        'Obter',
        'Criar',
        'Cadastrar',
        'Substituir',
        'Atualizar',
        'Remover',
        'Autenticar',
        'Renovar',
        'Encerrar',
        'Revogar',
        'Definir',
        'Verificar',
        'Iniciar',
        'Concluir',
        'Vincular',
        'Reenviar',
        'Solicitar',
        'Redefinir',
        'Alterar',
        'Aprovar',
        'Rejeitar',
        'Designar',
    ];

    $problems = [];

    foreach (apiDocValue($doc, 'paths') as $path => $operations) {
        foreach ($operations as $method => $operation) {
            $summary = trim((string) ($operation['summary'] ?? ''));
            $label = sprintf('%s %s', strtoupper((string) $method), (string) $path);

            if ('' === $summary) {
                $problems[] = sprintf('%s: summary vazio.', $label);

                continue;
            }

            if (str_ends_with($summary, '.') || str_ends_with($summary, ':')) {
                $problems[] = sprintf('%s: summary não deve terminar com pontuação ("%s").', $label, $summary);
            }

            if (str_contains($summary, '(') || str_contains($summary, ')')) {
                $problems[] = sprintf('%s: summary não deve conter parênteses ("%s").', $label, $summary);
            }

            if (mb_strlen($summary) > 60) {
                $problems[] = sprintf('%s: summary com %d caracteres (máximo 60).', $label, mb_strlen($summary));
            }

            $firstWord = explode(' ', $summary)[0];

            if (!in_array($firstWord, $approvedVerbs, true)) {
                $problems[] = sprintf(
                    '%s: summary deve começar com verbo no infinitivo aprovado, veio "%s".',
                    $label,
                    $firstWord,
                );
            }
        }
    }

    expect($problems)->toBe([]);
});

it('keeps bearerAuth in sync with IsGranted', function () {
    $doc = readApiDoc($this);

    $router = $this->api->getKernelBrowser()->getContainer()->get('router');

    $routesByPath = [];

    foreach ($router->getRouteCollection() as $route) {
        $routesByPath[$route->getPath()] = $route;
    }

    $problems = [];

    foreach (apiDocValue($doc, 'paths') as $path => $operations) {
        $route = $routesByPath[$path] ?? null;

        if (null === $route) {
            $problems[] = sprintf('%s: rota não encontrada no router.', (string) $path);

            continue;
        }

        $controller = $route->getDefault('_controller');

        if (is_array($controller) && is_string($controller[0] ?? null) && is_string($controller[1] ?? null)) {
            [$class, $method] = $controller;
        } elseif (is_string($controller) && str_contains($controller, '::')) {
            [$class, $method] = explode('::', $controller, 2);
        } else {
            $problems[] = sprintf('%s: controller não reconhecido.', (string) $path);

            continue;
        }

        if (!class_exists($class) || !method_exists($class, $method)) {
            $problems[] = sprintf('%s: controller %s::%s não encontrado.', (string) $path, $class, $method);

            continue;
        }

        $hasIsGranted = [] !== (new ReflectionClass($class))->getAttributes(IsGranted::class)
            || [] !== (new ReflectionMethod($class, $method))->getAttributes(IsGranted::class);

        foreach ($operations as $httpMethod => $operation) {
            $hasBearerAuth = false;

            foreach ($operation['security'] ?? [] as $requirement) {
                if (is_array($requirement) && array_key_exists('bearerAuth', $requirement)) {
                    $hasBearerAuth = true;

                    break;
                }
            }

            if ($hasIsGranted && !$hasBearerAuth) {
                $problems[] = sprintf(
                    '%s %s (%s::%s): tem #[IsGranted] mas não documenta bearerAuth.',
                    strtoupper((string) $httpMethod),
                    (string) $path,
                    $class,
                    $method,
                );
            }

            if (!$hasIsGranted && $hasBearerAuth) {
                $problems[] = sprintf(
                    '%s %s (%s::%s): é público mas documenta bearerAuth.',
                    strtoupper((string) $httpMethod),
                    (string) $path,
                    $class,
                    $method,
                );
            }
        }
    }

    expect($problems)->toBe([]);
});

it('documents every public api operation', function () {
    $doc = readApiDoc($this);

    $paths = apiDocValue($doc, 'paths');

    expect($paths)->toBeArray()
        ->and(array_keys($paths))->toEqualCanonicalizing([
            '/api/v1/auth/register',
            '/api/v1/auth/login',
            '/api/v1/auth/refresh',
            '/api/v1/auth/logout',
            '/api/v1/auth/logout/all',
            '/api/v1/auth/social/{provider}/start',
            '/api/v1/auth/social/{provider}/callback',
            '/api/v1/auth/social/{provider}/link',
            '/api/v1/auth/email/verify',
            '/api/v1/auth/password/forgot',
            '/api/v1/auth/password/reset',
            '/api/v1/users/me',
            '/api/v1/users/me/cpf',
            '/api/v1/users/me/identities/{provider}',
            '/api/v1/users/me/onboarding',
            '/api/v1/users/me/profile',
            '/api/v1/users/me/photo',
            '/api/v1/users/me/groups',
            '/api/v1/auth/email/verification/resend',
            '/api/v1/users/me/password',
            '/api/v1/groups',
            '/api/v1/groups/{id}',
            '/api/v1/groups/{id}/membership-requests',
            '/api/v1/groups/{id}/memberships',
            '/api/v1/groups/{id}/memberships/{membershipId}/approve',
            '/api/v1/groups/{id}/memberships/{membershipId}/reject',
            '/api/v1/groups/{id}/memberships/{membershipId}/remove',
            '/api/v1/groups/{id}/memberships/{membershipId}/role',
            '/api/v1/groups/{id}/supervisor',
            '/api/health/liveness',
            '/api/health/readiness',
        ]);
});

it('documents the register success with a named example', function () {
    $doc = readApiDoc($this);

    $example = apiDocValue(
        $doc,
        'paths', '/api/v1/auth/register', 'post', 'responses',
        '201', 'content', 'application/json', 'examples', 'USUARIO_CADASTRADO',
    );

    expect($example['summary'])->toBe('Usuário cadastrado')
        ->and($example['description'])->not->toBeEmpty()
        ->and(array_keys($example['value']))->toBe(['data', 'meta'])
        ->and($example['value']['data'])->toHaveKeys([
            'id', 'cpf', 'email', 'name', 'level', 'status', 'createdAt',
        ])
        ->and($example['value']['meta'])->toHaveKey('timestamp');
});

it('derives the health example values from the response schema', function () {
    $doc = readApiDoc($this);

    $liveness = apiDocValue(
        $doc,
        'paths', '/api/health/liveness', 'get', 'responses',
        '200', 'content', 'application/json', 'examples', 'APLICACAO_OPERANDO',
    );

    $healthy = apiDocValue(
        $doc,
        'paths', '/api/health/readiness', 'get', 'responses',
        '200', 'content', 'application/json', 'examples', 'SERVICO_SAUDAVEL',
    );

    $degraded = apiDocValue(
        $doc,
        'paths', '/api/health/readiness', 'get', 'responses',
        '503', 'content', 'application/json', 'examples', 'SERVICO_DEGRADADO',
    );

    expect($liveness['value']['data'])->toBe(['status' => 'UP'])
        ->and(array_keys($liveness['value']))->toBe(['data', 'meta'])
        ->and($liveness['value']['meta'])->toHaveKey('timestamp')
        ->and($healthy['value']['data'])->toBe(['status' => 'HEALTHY', 'checks' => ['database' => 'UP']])
        ->and(array_keys($healthy['value']))->toBe(['data', 'meta'])
        ->and($healthy['value']['meta'])->toHaveKey('timestamp')
        ->and($degraded['value']['data'])->toBe(['status' => 'UNHEALTHY', 'checks' => ['database' => 'DOWN']])
        ->and(array_keys($degraded['value']))->toBe(['data', 'meta'])
        ->and($degraded['value']['meta'])->toHaveKey('timestamp');
});

it('fills a value for every named example', function () {
    $doc = readApiDoc($this);

    $missing = [];

    foreach (apiDocValue($doc, 'paths') as $path => $operations) {
        foreach ($operations as $method => $operation) {
            foreach ($operation['responses'] ?? [] as $status => $response) {
                foreach ($response['content'] ?? [] as $mediaType => $content) {
                    foreach ($content['examples'] ?? [] as $name => $example) {
                        if (!isset($example['value'])) {
                            $missing[] = sprintf('%s %s %s %s %s', strtoupper((string) $method), $path, $status, $mediaType, $name);
                        }
                    }
                }
            }
        }
    }

    expect($missing)->toBe([]);
});

it('derives meta for every error example and keeps it last', function () {
    $doc = readApiDoc($this);

    $missingMeta = [];
    $metaNotLast = [];

    foreach (apiDocValue($doc, 'paths') as $path => $operations) {
        foreach ($operations as $method => $operation) {
            foreach ($operation['responses'] ?? [] as $status => $response) {
                foreach ($response['content'] ?? [] as $mediaType => $content) {
                    foreach ($content['examples'] ?? [] as $name => $example) {
                        $value = $example['value'] ?? null;
                        $label = sprintf('%s %s %s %s %s', strtoupper((string) $method), $path, $status, $mediaType, $name);

                        if (!is_array($value)) {
                            continue;
                        }

                        if (array_key_exists('meta', $value) && 'meta' !== array_key_last($value)) {
                            $metaNotLast[] = $label;
                        }

                        if ((int) $status >= 400 && !isset($value['meta']['timestamp'])) {
                            $missingMeta[] = $label;
                        }
                    }
                }
            }
        }
    }

    expect($missingMeta)->toBe([])
        ->and($metaNotLast)->toBe([]);
});

it('keeps every error example in the api error shape', function () {
    $doc = readApiDoc($this);

    $problems = [];

    foreach (apiDocValue($doc, 'paths') as $path => $operations) {
        foreach ($operations as $method => $operation) {
            foreach ($operation['responses'] ?? [] as $status => $response) {
                $content = $response['content']['application/json'] ?? [];
                $isErrorEnvelope = '#/components/schemas/ApiResponseError' === ($content['schema']['allOf'][0]['$ref'] ?? null);

                if (!$isErrorEnvelope) {
                    continue;
                }

                $fieldEnum = $content['schema']['allOf'][1]['properties']['errors']['items']['properties']['details']['items']['properties']['field']['enum'] ?? [];
                $fieldEnum = is_array($fieldEnum) ? $fieldEnum : [];

                foreach ($content['examples'] ?? [] as $name => $example) {
                    $errors = $example['value']['errors'] ?? null;
                    $label = sprintf('%s %s %s %s', strtoupper((string) $method), $path, $status, $name);

                    if (!is_array($errors) || [] === $errors) {
                        $problems[] = $label.': sem errors no value';

                        continue;
                    }

                    foreach ($errors as $error) {
                        if (!is_array($error)) {
                            $problems[] = $label.': errors deve conter objetos';

                            continue;
                        }

                        if (($error['code'] ?? null) !== $name) {
                            $problems[] = $label.': code divergente da chave';
                        }

                        if (!isset($error['message']) || '' === $error['message']) {
                            $problems[] = $label.': message ausente';
                        }

                        $extra = array_diff(array_keys($error), ['code', 'message', 'details']);

                        if ([] !== $extra) {
                            $problems[] = $label.': chaves inesperadas '.implode(', ', $extra);
                        }

                        $details = $error['details'] ?? [];

                        if (!is_array($details)) {
                            $problems[] = $label.': details deve ser lista';

                            continue;
                        }

                        foreach ($details as $detail) {
                            if (!is_array($detail) || !is_string($detail['field'] ?? null)) {
                                $problems[] = $label.': detail sem field';

                                continue;
                            }

                            if (!in_array($detail['field'], $fieldEnum, true)) {
                                $problems[] = $label.': field '.$detail['field'].' fora do enum';
                            }
                        }
                    }
                }
            }
        }
    }

    expect($problems)->toBe([]);
});

it('documents the register error codes with named examples', function () {
    $doc = readApiDoc($this);

    $responseStatuses = [];
    $operations = apiDocValue($doc, 'paths', '/api/v1/auth/register');
    expect($operations)->toBeArray();

    $responses = apiDocValue($operations, 'post', 'responses');
    expect($responses)->toBeArray();

    $responseStatuses = array_keys($responses);

    expect($responseStatuses)->toEqualCanonicalizing([
        '201',
        '400',
        '409',
        '415',
        '422',
        '429',
        '500',
    ]);

    $validationCodes = apiDocValue(
        $responses,
        '422', 'content', 'application/json', 'schema', 'allOf', 1,
        'properties', 'errors', 'items', 'properties', 'code', 'enum',
    );

    expect($validationCodes)->toBe(['VALIDATION_ERROR', 'CPF_INVALID', 'EMAIL_INVALID']);

    $validationExamples = apiDocValue($responses, '422', 'content', 'application/json', 'examples');

    expect(array_keys($validationExamples))->toBe(['VALIDATION_ERROR', 'CPF_INVALID', 'EMAIL_INVALID']);

    $validationError = apiDocValue($responses, '422', 'content', 'application/json', 'examples', 'VALIDATION_ERROR');

    expect(array_keys($validationError['value']))->toBe(['errors', 'meta'])
        ->and($validationError['value']['meta']['timestamp'])->toBe('2026-09-21T10:00:00.000+00:00');

    $conflictCodes = apiDocValue(
        $responses,
        '409', 'content', 'application/json', 'schema', 'allOf', 1,
        'properties', 'errors', 'items', 'properties', 'code', 'enum',
    );

    expect($conflictCodes)->toBe(['USER_CPF_ALREADY_EXISTS', 'EMAIL_ALREADY_EXISTS']);

    expect(array_keys(apiDocValue($responses, '409', 'content', 'application/json', 'examples')))
        ->toBe(['USER_CPF_ALREADY_EXISTS', 'EMAIL_ALREADY_EXISTS'])
        ->and(array_keys(apiDocValue($responses, '400', 'content', 'application/json', 'examples')))
        ->toBe(['BAD_REQUEST'])
        ->and(array_keys(apiDocValue($responses, '415', 'content', 'application/json', 'examples')))
        ->toBe(['UNSUPPORTED_MEDIA_TYPE']);
});

it('documents a generic internal error on every operation', function () {
    $doc = readApiDoc($this);

    $paths = apiDocValue($doc, 'paths');
    expect($paths)->toBeArray();

    foreach ($paths as $path => $operations) {
        expect($operations)->toBeArray();

        foreach ($operations as $method => $operation) {
            $responses = apiDocValue($operation, 'responses');
            expect($responses)->toHaveKey('500');

            expect(array_keys(apiDocValue($responses, '500', 'content', 'application/json', 'examples')))
                ->toBe(['INTERNAL_ERROR'], sprintf('%s %s deve documentar o erro interno.', strtoupper((string) $method), (string) $path));
        }
    }
});

it('does not leak internal or unreachable error codes', function (string $internalCode) {
    $doc = readApiDoc($this);

    expect((string) json_encode($doc, JSON_THROW_ON_ERROR))->not->toContain($internalCode);
})->with([
    'USER_LEVEL_INVALID',
    'USER_ALREADY_ACTIVE',
    'USER_ALREADY_SUSPENDED',
    'DATE_TIME_INVALID',
    'INVALID_UUID',
    'RESOURCE_CONFLICT',
]);

/**
 * @param class-string $dtoClass
 *
 * @return list<string>
 */
function constrainedPropertiesOf(string $dtoClass): array
{
    $properties = [];

    foreach ((new ReflectionClass($dtoClass))->getProperties() as $property) {
        $constraints = $property->getAttributes(Constraint::class, ReflectionAttribute::IS_INSTANCEOF);

        if ([] === $constraints) {
            continue;
        }

        $properties[] = $property->getName();
    }

    return $properties;
}

it('keeps the documented validation fields in sync with the request constraints', function () {
    $doc = readApiDoc($this);

    $documentedFields = apiDocValue(
        $doc,
        'paths', '/api/v1/auth/register', 'post', 'responses',
        '422', 'content', 'application/json', 'schema', 'allOf', 1,
        'properties', 'errors', 'items', 'properties', 'details', 'items', 'properties', 'field', 'enum',
    );

    expect($documentedFields)->toEqualCanonicalizing(constrainedPropertiesOf(RegisterUserRequest::class));
});

it('keeps the documented conflict fields in sync with the register rules', function () {
    $doc = readApiDoc($this);

    $documentedFields = apiDocValue(
        $doc,
        'paths', '/api/v1/auth/register', 'post', 'responses',
        '409', 'content', 'application/json', 'schema', 'allOf', 1,
        'properties', 'errors', 'items', 'properties', 'details', 'items', 'properties', 'field', 'enum',
    );

    expect($documentedFields)->toEqualCanonicalizing(['cpf', 'email']);
});

it('documents the social callback link ticket meta', function () {
    $doc = readApiDoc($this);

    $responses = apiDocValue(
        $doc,
        'paths', '/api/v1/auth/social/{provider}/callback', 'post', 'responses',
    );

    $example = apiDocValue(
        $responses,
        '409', 'content', 'application/json', 'examples', 'SOCIAL_ACCOUNT_LINK_REQUIRED',
    );

    expect($example['value']['meta'])->toHaveKeys(['timestamp', 'link'])
        ->and($example['value']['meta']['link'])->toHaveKeys(['token', 'expiresAt']);

    $metaSchema = apiDocValue(
        $responses,
        '409', 'content', 'application/json', 'schema', 'allOf', 1,
        'properties', 'meta',
    );

    expect($metaSchema['allOf'][0]['$ref'])->toBe('#/components/schemas/ApiResponseMeta')
        ->and($metaSchema['allOf'][1]['properties']['link'])->toHaveKeys(['type', 'properties'])
        ->and($metaSchema['allOf'][1]['required'])->toBe(['link']);
});

it('does not leak the link meta into other conflicts', function () {
    $doc = readApiDoc($this);

    $example = apiDocValue(
        $doc,
        'paths', '/api/v1/auth/register', 'post', 'responses',
        '409', 'content', 'application/json', 'examples', 'EMAIL_ALREADY_EXISTS',
    );

    expect($example['value']['meta'])->not->toHaveKey('link');
});

it('documents the paginated group list with pagination meta', function () {
    $doc = readApiDoc($this);

    $properties = apiDocValue(
        $doc,
        'paths', '/api/v1/groups', 'get', 'responses',
        '200', 'content', 'application/json', 'schema', 'allOf', 1, 'properties',
    );

    expect($properties['data']['type'])->toBe('array')
        ->and($properties['data']['items']['$ref'])->toBe('#/components/schemas/GroupSummaryResponse')
        ->and($properties['meta']['$ref'])->toBe('#/components/schemas/ApiResponsePaginatedMeta');
});

it('does not document pagination on plain lists', function () {
    $doc = readApiDoc($this);

    $paths = [
        '/api/v1/users/me/groups' => '#/components/schemas/GroupSummaryResponse',
        '/api/v1/groups/{id}/memberships' => '#/components/schemas/MembershipResponse',
    ];

    foreach ($paths as $path => $itemRef) {
        $properties = apiDocValue(
            $doc,
            'paths', $path, 'get', 'responses',
            '200', 'content', 'application/json', 'schema', 'allOf', 1, 'properties',
        );

        expect($properties)->toHaveKey('data')
            ->and($properties)->not->toHaveKey('meta')
            ->and($properties['data']['type'])->toBe('array')
            ->and($properties['data']['items']['$ref'])->toBe($itemRef);
    }
});
