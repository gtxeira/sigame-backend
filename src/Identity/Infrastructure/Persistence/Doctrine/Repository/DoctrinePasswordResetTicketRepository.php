<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Repository;

use App\Identity\Domain\PasswordReset\Entity\PasswordResetTicket;
use App\Identity\Domain\PasswordReset\Repository\PasswordResetTicketRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implementação Doctrine dos tickets de reset de senha.
 *
 * @extends AbstractRepository<PasswordResetTicket>
 */
final class DoctrinePasswordResetTicketRepository extends AbstractRepository implements PasswordResetTicketRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetTicket::class);
    }

    public function save(PasswordResetTicket $ticket): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($ticket);
        $entityManager->flush();
    }

    public function findByHash(string $tokenHash): ?PasswordResetTicket
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function markConsumed(PasswordResetTicket $ticket): bool
    {
        $affected = $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->where('t.id = :id')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('id', $ticket->id()->value())
            ->getQuery()
            ->execute();

        return $affected > 0;
    }

    public function invalidateActive(User $user): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->where('IDENTITY(t.user) = :userId')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', Instant::now(), 'instant')
            ->setParameter('userId', $user->id()->value())
            ->getQuery()
            ->execute();
    }
}
