<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Repository;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

/**
 * Porta de persistência do agregado Group.
 */
interface GroupRepositoryInterface
{
    public function save(Group $group): void;

    public function findById(GroupId $id): ?Group;

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

    public function countApprovedMemberships(MemberUserId $userId): int;

    public function search(GroupSearchCriteria $criteria): GroupPage;

    /**
     * @return list<Group>
     */
    public function findApprovedByMember(MemberUserId $userId): array;
}
