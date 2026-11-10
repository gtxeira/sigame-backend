<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Repository;

use App\Notifications\Application\Exception\NotificationAlreadyExistsException;
use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\Repository\NotificationPage;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\Repository\NotificationSearchCriteria;
use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use App\Notifications\Domain\Notification\ValueObject\NotificationReadFilter;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * Implementação Doctrine do inbox de notificações.
 *
 * @extends AbstractRepository<Notification>
 */
final class DoctrineNotificationRepository extends AbstractRepository implements NotificationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function save(Notification $notification): void
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($notification);
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new NotificationAlreadyExistsException($exception->getMessage(), 0, $exception);
        }
    }

    public function transactional(callable $operation): mixed
    {
        $entityManager = $this->getEntityManager();

        if ($entityManager->getConnection()->isTransactionActive()) {
            return $operation();
        }

        return $entityManager->wrapInTransaction($operation);
    }

    public function findForRecipient(NotificationId $id, RecipientId $recipientId): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('n.recipientId = :recipientId')
            ->setParameter('id', $id, 'notification_id')
            ->setParameter('recipientId', $recipientId, 'notification_recipient_id')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByDeduplicationKey(
        RecipientId $recipientId,
        DeduplicationKey $key,
    ): ?Notification {
        return $this->createQueryBuilder('n')
            ->where('n.recipientId = :recipientId')
            ->andWhere('n.deduplicationKey = :deduplicationKey')
            ->setParameter('recipientId', $recipientId, 'notification_recipient_id')
            ->setParameter('deduplicationKey', $key->value())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function search(NotificationSearchCriteria $criteria): NotificationPage
    {
        $queryBuilder = $this->createQueryBuilder('n')
            ->where('n.recipientId = :recipientId')
            ->setParameter('recipientId', $criteria->recipientId, 'notification_recipient_id');

        if (NotificationReadFilter::UNREAD === $criteria->read) {
            $queryBuilder->andWhere('n.readAt IS NULL');
        }

        if (NotificationReadFilter::READ === $criteria->read) {
            $queryBuilder->andWhere('n.readAt IS NOT NULL');
        }

        if (null !== $criteria->category) {
            $queryBuilder
                ->andWhere('n.category = :category')
                ->setParameter('category', $criteria->category->value);
        }

        $countQueryBuilder = clone $queryBuilder;
        $total = (int) $countQueryBuilder
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Notification> $items */
        $items = $queryBuilder
            ->orderBy('n.createdAt', SortDirection::Descending)
            ->addOrderBy('n.id', SortDirection::Descending)
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        return new NotificationPage(items: $items, total: $total);
    }

    public function countUnread(RecipientId $recipientId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientId = :recipientId')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('recipientId', $recipientId, 'notification_recipient_id')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllRead(RecipientId $recipientId, Instant $now): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update(Notification::class, 'n')
            ->set('n.readAt', ':now')
            ->where('n.recipientId = :recipientId')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('recipientId', $recipientId, 'notification_recipient_id')
            ->setParameter('now', $now, 'instant')
            ->getQuery()
            ->execute();
    }
}
