<?php

declare(strict_types=1);

namespace App\Groups\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros de domínio esperados do módulo Groups.
 *
 * Único ponto autorizado a montar um DomainError para estes códigos,
 * evitando divergência de mensagem, field e kind entre chamadas.
 */
final class GroupErrors
{
    public static function groupNotFound(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupNotFound,
            message: 'O grupo não foi encontrado.',
        );
    }

    public static function membershipNotFound(): DomainError
    {
        return self::error(
            code: GroupErrorCode::MembershipNotFound,
            message: 'A participação no grupo não foi encontrada.',
        );
    }

    public static function nameInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupNameInvalid,
            message: sprintf('O nome do grupo deve ter entre %d e %d caracteres.', $minimum, $maximum),
            field: 'name',
        );
    }

    public static function descriptionInvalid(int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupDescriptionInvalid,
            message: sprintf('A descrição do grupo deve ter no máximo %d caracteres.', $maximum),
            field: 'description',
        );
    }

    public static function communicationLinkInvalid(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupCommunicationLinkInvalid,
            message: 'O link de comunicação deve ser uma URL http ou https válida.',
            field: 'communicationLink',
        );
    }

    public static function removalReasonRequired(int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupRemovalReasonRequired,
            message: sprintf('O motivo da remoção é obrigatório e deve ter no máximo %d caracteres.', $maximum),
            field: 'reason',
        );
    }

    public static function supervisorNotMember(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupSupervisorNotMember,
            message: 'O supervisor deve ser um membro aprovado do grupo.',
            field: 'membershipId',
        );
    }

    public static function levelInsufficient(int $minimum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupLevelInsufficient,
            message: sprintf('É necessário estar no nível %d ou superior para criar um grupo.', $minimum),
            field: 'level',
        );
    }

    public static function adminRequired(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupAdminRequired,
            message: 'Apenas administradores do grupo podem executar esta operação.',
        );
    }

    public static function limitReached(int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupLimitReached,
            message: sprintf('O usuário já participa do número máximo de %d grupos.', $maximum),
        );
    }

    public static function groupFull(int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupFull,
            message: sprintf('O grupo já possui o número máximo de %d membros aprovados.', $maximum),
        );
    }

    public static function membershipAlreadyExists(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMembershipAlreadyExists,
            message: 'Já existe uma solicitação pendente ou uma participação aprovada neste grupo.',
        );
    }

    public static function membershipNotPending(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMembershipNotPending,
            message: 'A solicitação não está pendente e não pode ser decidida.',
        );
    }

    public static function membershipNotApproved(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMembershipNotApproved,
            message: 'A participação não está aprovada.',
        );
    }

    public static function lastAdminRequired(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupLastAdminRequired,
            message: 'O grupo precisa manter ao menos um administrador aprovado.',
        );
    }

    private static function error(
        GroupErrorCode $code,
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
