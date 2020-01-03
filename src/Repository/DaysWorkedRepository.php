<?php

namespace App\Repository;

use App\Entity\DaysWorked;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method DaysWorked|null find($id, $lockMode = null, $lockVersion = null)
 * @method DaysWorked|null findOneBy(array $criteria, array $orderBy = null)
 * @method DaysWorked[]    findAll()
 * @method DaysWorked[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DaysWorkedRepository extends ServiceEntityRepository
{

	/**
	 * @return DaysWorked[]
	 */
    public function findAllOrdered()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT dp
            FROM App\Entity\DaysWorked dp
            ORDER BY dp.displayOrder ASC
            "
        );
        return $query->execute();
    }

    /**
     * @param $day
     * @return DaysWorked
     * @throws NonUniqueResultException
     */
    public function findByDayAndWorked($day)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT dp
            FROM App\Entity\DaysWorked dp
            WHERE dp.day LIKE :day AND dp.worked = 1
            "
        )->setParameter('day', $day);
        return $query->getOneOrNullResult();
    }

	/**
	 * @return int
	 * @throws NonUniqueResultException
	 */
    public function countEmptyTimes()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(dw)
            FROM App\Entity\DaysWorked dw
            WHERE dw.times IS NULL AND dw.worked = 1
            ");
        return $query->getSingleScalarResult();
    }
}
