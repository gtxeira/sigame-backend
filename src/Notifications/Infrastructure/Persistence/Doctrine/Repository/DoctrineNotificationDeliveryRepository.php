<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Repository;

use App\Notifications\Domain\Notification\Entity\NotificationDelivery;
use App\Notifications\Domain\Notification\Repository\NotificationDeliveryRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationDeliveryId;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use App\Shared\Infrastructure\Persistence\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Implementação Doctrine das entregas externas.
 *
 * @extends AbstractRepository<NotificationDelivery>
 */
final class DoctrineNotificationDeliveryRepository extends AbstractRepository implements NotificationDeliveryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDelivery::class);
    }

    public function save(NotificationDelivery $delivery): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($delivery);
        $entityManager->flush();
    }

    public function findById(NotificationDeliveryId $id): ?NotificationDelivery
    {
        return $this->find($id->value());
    }

    public function findForNotificationAndChannel(
        NotificationId $notificationId,
        NotificationChannel $channel,
    ): ?NotificationDelivery {
        return $this->createQueryBuilder('d')
            ->where('IDENTITY(d.notification) = :notificationId')
            ->andWhere('d.channel = :channel')
            ->setParameter('notificationId', $notificationId->value())
            ->setParameter('channel', $channel->value)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
