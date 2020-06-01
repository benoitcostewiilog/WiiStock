<?php

namespace App\Repository;

use App\Entity\WorkFreeDay;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @method WorkFreeDay|null   find($id, $lockMode = null, $lockVersion = null)
 * @method WorkFreeDay|null   findOneBy(array $criteria, array $orderBy = null)
 * @method WorkFreeDay[]      findAll()
 * @method WorkFreeDay[]      findBy(array $criteria, array $orderBy = null, $limite = null, $offset = null)
 */
class WorkFreeDayRepository extends EntityRepository
{
    /**
     * @return DateTime[]
     */
    public function  getWorkFreeDaysToString()
    {
        $em = $this->getEntityManager();
        $query = $em->createQueryBuilder();
        return $query->select('day.day')->from('App\Entity\WorkFreeDay', 'day')->getQuery()->execute();
    }
}