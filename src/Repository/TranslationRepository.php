<?php

namespace App\Repository;

use App\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

	/**
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function countUpdatedRows()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT COUNT(t)
			FROM App\Entity\Translation t
			WHERE t.updated = 1");

		return $query->getSingleScalarResult();
	}

	public function clearUpdate()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"UPDATE App\Entity\Translation t
		SET t.updated = 0"
		);

		return $query->execute();
	}

	/**
	 * @return string[]
	 */
	public function getMenus()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT DISTINCT (t.menu)
		FROM App\Entity\Translation t");

		return $query->getScalarResult();
	}

}
