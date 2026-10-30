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
