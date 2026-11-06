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
    case BadRequest = 'BAD_REQUEST';
    case Unauthorized = 'UNAUTHORIZED';
    case AccessTokenMissing = 'ACCESS_TOKEN_MISSING';
    case AccessTokenInvalid = 'ACCESS_TOKEN_INVALID';
    case AccessTokenExpired = 'ACCESS_TOKEN_EXPIRED';
    case Forbidden = 'FORBIDDEN';
    case OriginNotAllowed = 'ORIGIN_NOT_ALLOWED';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case UnsupportedMediaType = 'UNSUPPORTED_MEDIA_TYPE';
    case ValidationError = 'VALIDATION_ERROR';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case InternalError = 'INTERNAL_ERROR';
    case HttpError = 'HTTP_ERROR';

    /**
     * Mensagem pública do erro.
     *
     * Nunca inclui detalhes internos (mensagens de exceção, nomes de classes,
     * dados de infraestrutura): é o texto que o cliente pode exibir.
     */
    public function message(): string
    {
        return match ($this) {
            self::BadRequest => 'A requisição contém dados inválidos.',
            self::Unauthorized => 'Autenticação necessária.',
            self::AccessTokenMissing => 'Token de acesso ausente.',
            self::AccessTokenInvalid => 'Token de acesso inválido.',
            self::AccessTokenExpired => 'Token de acesso expirado. Renove a sessão.',
            self::Forbidden => 'Acesso negado.',
            self::OriginNotAllowed => 'A origem da requisição não é permitida para esta operação.',
            self::NotFound => 'Recurso não encontrado.',
            self::MethodNotAllowed => 'O método HTTP não é permitido para este recurso.',
            self::UnsupportedMediaType => 'O formato do conteúdo enviado não é suportado. Utilize application/json.',
            self::ValidationError => 'Um ou mais erros de validação foram encontrados.',
            self::TooManyRequests => 'Muitas tentativas. Tente novamente em instantes.',
            self::InternalError => 'Um erro interno ocorreu. Por favor, aguarde e tente novamente.',
            self::HttpError => 'Não foi possível processar a requisição.',
        };
    }
}
