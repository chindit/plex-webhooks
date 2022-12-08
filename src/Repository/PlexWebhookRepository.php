<?php

namespace App\Repository;

use App\Entity\PlexWebhook;
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

    public function findNewMoviesFromLastWeek(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.createdAt > :lastWeek')
            ->andWhere('p.type = :type')
            ->setParameter('lastWeek', (new \DateTimeImmutable())->sub(new \DateInterval('P1W')))
            ->setParameter('type', 'library.new')
            ->getQuery()
            ->getResult();
    }
//    /**
//     * @return PlexWebhook[] Returns an array of PlexWebhook objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PlexWebhook
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
