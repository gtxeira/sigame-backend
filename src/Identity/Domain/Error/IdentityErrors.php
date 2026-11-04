<?php

declare(strict_types=1);

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros de domínio esperados do módulo Identity.
 *
 * Único ponto autorizado a montar um DomainError para estes códigos,
 * evitando divergência de mensagem, field e kind entre chamadas.
 */
final class IdentityErrors
{
    public static function cpfAlreadyExists(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserCpfAlreadyExists,
            message: 'Já existe um usuário com este CPF.',
            field: 'cpf',
        );
    }

    public static function cpfAlreadySet(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserCpfAlreadySet,
            message: 'O CPF já foi definido e não pode ser alterado.',
            field: 'cpf',
        );
    }

    public static function emailAlreadyExists(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::EmailAlreadyExists,
            message: 'Já existe um usuário com este e-mail.',
            field: 'email',
        );
    }

    public static function userLevelBelowMinimum(int $minimum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserLevelInvalid,
            message: 'O nível do usuário deve ser maior ou igual a '.$minimum,
            field: 'level',
        );
    }

    public static function userLevelAboveMaximum(int $maximum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserLevelInvalid,
            message: 'O nível do usuário deve ser menor ou igual a '.$maximum,
            field: 'level',
        );
    }

    public static function userHeightInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserHeightInvalid,
            message: sprintf('A altura deve estar entre %d e %d centímetros.', $minimum, $maximum),
            field: 'heightCm',
        );
    }

    public static function userWeightInvalid(float $minimum, float $maximum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserWeightInvalid,
            message: sprintf('O peso deve estar entre %s e %s quilogramas.', $minimum, $maximum),
            field: 'weightKg',
        );
    }

    public static function invalidCredentials(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::InvalidCredentials,
            message: 'E-mail ou senha inválidos.',
            field: 'credentials',
        );
    }

    public static function accountSuspended(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::AccountSuspended,
            message: 'Esta conta está suspensa e não pode autenticar.',
            field: 'status',
        );
    }

    public static function refreshTokenInvalid(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::RefreshTokenInvalid,
            message: 'O token de renovação é inválido.',
        );
    }

    public static function refreshTokenExpired(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::RefreshTokenExpired,
            message: 'O token de renovação expirou. Autentique-se novamente.',
        );
    }

    public static function refreshTokenReused(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::RefreshTokenReused,
            message: 'O token de renovação já foi utilizado. Autentique-se novamente.',
        );
    }

    public static function refreshTokenAlreadyRotated(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::RefreshTokenAlreadyRotated,
            message: 'O token de renovação acabou de ser rotacionado.',
        );
    }

    public static function refreshInProgress(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::RefreshInProgress,
            message: 'Já existe uma renovação em andamento para esta sessão.',
        );
    }

    public static function socialProviderUnsupported(string $provider): DomainError
    {
        return self::error(
            code: IdentityErrorCode::SocialProviderUnsupported,
            message: sprintf('O provedor de login "%s" não é suportado.', $provider),
            field: 'provider',
        );
    }

    public static function socialAuthenticationFailed(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::SocialAuthenticationFailed,
            message: 'Não foi possível autenticar com o provedor social.',
        );
    }

    public static function socialEmailNotVerified(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::SocialEmailNotVerified,
            message: 'O provedor social não confirmou a verificação do e-mail.',
            field: 'email',
        );
    }

    public static function socialEmailMissing(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::SocialEmailMissing,
            message: 'O provedor social não retornou um e-mail.',
            field: 'email',
        );
    }

    private static function error(
        IdentityErrorCode $code,
        string $message,
        ?string $field = null,
    ): DomainError {
        return new DomainError(
            code: $code->value,
            message: $message,
            kind: $code->kind(),
            field: $field,
        );
    }
}
