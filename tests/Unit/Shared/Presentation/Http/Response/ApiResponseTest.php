<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\Response\ApiResponse;

it('keeps an empty list as data in the success envelope', function () {
    $payload = ApiResponse::ok([])->jsonSerialize();

    expect($payload)->toHaveKey('data')
        ->and($payload['data'])->toBe([])
        ->and($payload['meta'])->toHaveKey('timestamp');
});

it('keeps null data in the success envelope', function () {
    $payload = ApiResponse::ok()->jsonSerialize();

    expect($payload)->toHaveKey('data')
        ->and($payload['data'])->toBeNull();
});

it('keeps falsy scalar data', function (mixed $data) {
    $payload = ApiResponse::ok($data)->jsonSerialize();

    expect($payload)->toHaveKey('data')
        ->and($payload['data'])->toBe($data);
})->with([
    'false' => false,
    'zero' => 0,
    'empty string' => '',
]);

it('does not include data in the error envelope', function () {
    $payload = ApiResponse::error([
        ['code' => 'SOME_ERROR', 'message' => 'Falhou.'],
    ])->jsonSerialize();

    expect($payload)->not->toHaveKey('data')
        ->and($payload['errors'])->toHaveCount(1)
        ->and($payload['meta'])->toHaveKey('timestamp');
});

it('keeps data and pagination meta together', function () {
    $payload = ApiResponse::ok([])
        ->withPagination(page: 2, perPage: 10, total: 0)
        ->jsonSerialize();

    expect($payload['data'])->toBe([])
        ->and($payload['meta']['pagination'])->toBe([
            'page' => 2,
            'perPage' => 10,
            'total' => 0,
            'totalPages' => 0,
        ]);
});
