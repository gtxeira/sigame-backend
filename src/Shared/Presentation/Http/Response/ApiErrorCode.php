<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Response;

/**
 * Catálogo de códigos de erro gerados pela camada HTTP.
 *
 * Diferente dos códigos de domínio, estes representam falhas
 * transversais de transporte/validação de entrada.
 */
enum ApiErrorCode: string
{
    case InternalError = 'INTERNAL_ERROR';
    case ValidationError = 'VALIDATION_ERROR';
    case NotFound = 'NOT_FOUND';
}
