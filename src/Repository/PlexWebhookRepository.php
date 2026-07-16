<?php

namespace App\Repository;

use App\Entity\PlexWebhook;
use App\Enum\PlexEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlexWebhook>
 *
 * @method PlexWebhook|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlexWebhook|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlexWebhook[]    findAll()
 * @method PlexWebhook[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlexWebhookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlexWebhook::class);
    }

    public function save(PlexWebhook $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PlexWebhook $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return PlexWebhook[]
     */
    public function findNewMoviesFromLastWeek(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.createdAt > :lastWeek')
            ->andWhere('p.type = :type')
            ->setParameter('lastWeek', (new \DateTimeImmutable())->sub(new \DateInterval('P1W')))
            ->setParameter('type', PlexEventType::LibraryNew->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * Deletes every webhook older than the given date and returns the number of rows removed.
     */
    public function deleteOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
