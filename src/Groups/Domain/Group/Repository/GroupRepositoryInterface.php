<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Repository;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Porta de persistência do agregado Group.
 */
interface GroupRepositoryInterface
{
    public function save(Group $group): void;

    public function findById(GroupId $id): ?Group;

    /**
     * Carrega o grupo, ignorando os que foram excluídos logicamente.
     */
    public function findByIdExcludingDeleted(GroupId $id): ?Group;

    /**
     * Carrega o grupo com lock pessimista para decisões concorrentes.
     */
    public function findByIdForUpdate(GroupId $id): ?Group;

    /**
     * Serializa operações do mesmo usuário entre grupos distintos.
     */
    public function lockUser(MemberUserId $userId): void;

    /**
     * Executa a operação em transação, reutilizando a transação já aberta
     * (por exemplo, a do DAMA nos testes).
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;

    /**
     * Participações aprovadas em grupos visíveis (não arquivados e não
     * excluídos). Inclui grupos criados pelo próprio usuário.
     */
    public function countApprovedMemberships(MemberUserId $userId): int;

    /**
     * Grupos em formação criados pelo usuário.
     */
    public function countFormingCreatedBy(MemberUserId $userId): int;

    /**
     * Indica se o usuário já possui grupo visível com o mesmo nome e
     * modalidade (comparação sem distinção de maiúsculas).
     */
    public function existsByCreatorAndName(
        MemberUserId $creator,
        GroupName $name,
        Modality $modality,
        ?GroupId $excludeGroupId = null,
    ): bool;

    /**
     * Grupos em formação sem atividade desde o instante informado.
     *
     * @return list<GroupId>
     */
    public function findIdleFormingGroupIds(Instant $idleSince): array;

    public function search(GroupSearchCriteria $criteria): GroupPage;

    /**
     * @return list<Group>
     */
    public function findApprovedByMember(MemberUserId $userId): array;
}
