<?php

namespace App\Repository;

use App\Entity\MailerServer;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method MailerServer|null find($id, $lockMode = null, $lockVersion = null)
 * @method MailerServer|null findOneBy(array $criteria, array $orderBy = null)
 * @method MailerServer[]    findAll()
 * @method MailerServer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailerServerRepository extends EntityRepository
{
	/**
	 * @return MailerServer|null
	 * @throws NonUniqueResultException
	 */
    public function findOneMailerServer()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT m
            FROM App\Entity\MailerServer m
            "
        );
        return $query->getOneOrNullResult();
    }

}
