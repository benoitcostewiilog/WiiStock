<?php

namespace App\Repository;

use App\Entity\Nature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Nature|null find($id, $lockMode = null, $lockVersion = null)
 * @method Nature|null findOneBy(array $criteria, array $orderBy = null)
 * @method Nature[]    findAll()
 * @method Nature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NatureRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Nature::class);
    }

    public function countUsedById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
            LEFT JOIN n.colis c
            WHERE c.nature = :id
           "
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
           "
        );

        return $query->getSingleScalarResult();
    }
}