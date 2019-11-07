<?php

namespace App\Repository;

use App\Entity\Arrivage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Arrivage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Arrivage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Arrivage[]    findAll()
 * @method Arrivage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArrivageRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Arrivage::class);
    }

	/**
	 * @param string $dateMin
	 * @param string $dateMax
	 * @return Arrivage[]|null
	 */
    public function findByDates($dateMin, $dateMax)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Arrivage a
            WHERE a.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

	public function countByFournisseur($fournisseurId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT COUNT(a)
			FROM App\Entity\Arrivage a
			WHERE a.fournisseur = :fournisseurId"
		)->setParameter('fournisseurId', $fournisseurId);

		return $query->getSingleScalarResult();
	}

	public function countByChauffeur($chauffeur)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT COUNT(a)
			FROM App\Entity\Arrivage a
			WHERE a.chauffeur = :chauffeur"
		)->setParameter('chauffeur', $chauffeur);

		return $query->getSingleScalarResult();
	}

	/**
	 * @param Arrivage $arrivage
	 * @return int
	 * @throws NonUniqueResultException
	 */
	public function countColisByArrivage($arrivage)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(c)
			FROM App\Entity\Colis c
			WHERE c.arrivage = :arrivage"
		)->setParameter('arrivage', $arrivage->getId());

		return $query->getSingleScalarResult();
	}

    public function getColisByArrivage($arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT c.code
			FROM App\Entity\Colis c
			WHERE c.arrivage = :arrivage"
        )->setParameter('arrivage', $arrivage);

        return $query->getScalarResult();
    }

    public function findOneByLitige($litige)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            'SELECT a
                FROM App\Entity\Arrivage a
                JOIN a.colis c
                JOIN c.litiges l
                WHERE l.id = :litige
                '
        )->setParameter('litige', $litige);

        $result = $query->execute();

        return $result ? $result[0] : null;
    }
}
