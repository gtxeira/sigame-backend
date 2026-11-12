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

    public static function memberLimitBelowApproved(int $requested, int $approved): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMemberLimitBelowApproved,
            message: sprintf(
                'O limite de %d membros é menor que os %d membros já aprovados no grupo.',
                $requested,
                $approved,
            ),
            field: 'maxMembers',
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

    public static function levelInsufficientToJoin(int $minimum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupLevelInsufficient,
            message: sprintf('É necessário estar no nível %d ou superior para entrar em um grupo.', $minimum),
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

    public static function ownerRequired(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupOwnerRequired,
            message: 'Apenas o responsável pelo grupo pode executar esta operação.',
        );
    }

    public static function membersOnly(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMembersOnly,
            message: 'Apenas participantes aprovados do grupo podem ver os membros.',
        );
    }

    public static function ownerTransferRequiredToLeave(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupOwnerTransferRequired,
            message: 'Transfira a responsabilidade pelo grupo antes de sair.',
        );
    }

    public static function ownerTransferRequiredToRemove(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupOwnerTransferRequired,
            message: 'Transfira a responsabilidade pelo grupo antes de remover o responsável.',
        );
    }

    public static function ownerTransferRequiredToDemote(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupOwnerTransferRequired,
            message: 'Transfira a responsabilidade pelo grupo antes de rebaixar o responsável.',
        );
    }

    public static function ownerSuspended(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMembershipSuspended,
            message: 'A conta do novo responsável está suspensa.',
            field: 'membershipId',
        );
    }

    public static function limitReached(int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupLimitReached,
            message: sprintf('O usuário já participa do número máximo de %d grupos.', $maximum),
        );
    }

    public static function formingLimitReached(int $maximum): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupFormingLimitReached,
            message: sprintf('O usuário já possui o número máximo de %d grupo em formação.', $maximum),
        );
    }

    public static function duplicateGroup(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupDuplicate,
            message: 'O usuário já criou um grupo com este nome e modalidade.',
            field: 'name',
        );
    }

    public static function groupArchived(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupArchived,
            message: 'O grupo está arquivado e não aceita esta operação.',
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

    public static function membershipSuspended(): DomainError
    {
        return self::error(
            code: GroupErrorCode::GroupMembershipSuspended,
            message: 'A conta do solicitante está suspensa e não pode ser aprovada.',
            field: 'membershipId',
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
