<?php

declare(strict_types=1);

use OpenApi\Attributes as OA;

function successMediaType(OA\Response $response): OA\MediaType
{
    expect($response->content)->toBeArray()->not->toBeEmpty();

    $content = $response->content[0];
    expect($content)->toBeInstanceOf(OA\MediaType::class);

    return $content;
}
