<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrors;
use App\Shared\Domain\Error\SharedErrors;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\Response\ErrorStatusResolver;
use App\Shared\Presentation\Http\Response\ResultResponder;
use Symfony\Component\HttpFoundation\Response;

it('returns 422 for validation failures', function () {
    $responder = new ResultResponder(new ErrorStatusResolver());

    $response = $responder->failure(
        Result::failure([SharedErrors::cpfInvalidCheckDigits()]),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('returns 409 for conflict failures', function () {
    $responder = new ResultResponder(new ErrorStatusResolver());

    $response = $responder->failure(
        Result::failure([IdentityErrors::cpfAlreadyExists()]),
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_CONFLICT);
});
