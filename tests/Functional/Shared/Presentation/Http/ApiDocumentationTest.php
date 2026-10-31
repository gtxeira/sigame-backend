<?php

declare(strict_types=1);

use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use Symfony\Component\HttpFoundation\Response;
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

it('documents every public api operation', function () {
    $doc = readApiDoc($this);

    $paths = apiDocValue($doc, 'paths');

    expect($paths)->toBeArray()
        ->and(array_keys($paths))->toEqualCanonicalizing([
            '/api/v1/auth/register',
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
    'INVALID_HASHED_PASSWORD',
    'USER_LEVEL_INVALID',
    'USER_HEIGHT_INVALID',
    'USER_WEIGHT_INVALID',
    'ONBOARDING_ALREADY_COMPLETED',
    'ONBOARDING_INVALID_PRIMARY_MODALITY',
    'ONBOARDING_NO_MODALITY_SELECTED',
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
