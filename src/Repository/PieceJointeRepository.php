<?php

namespace App\Repository;

use App\Entity\PieceJointe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PieceJointe|null find($id, $lockMode = null, $lockVersion = null)
 * @method PieceJointe|null findOneBy(array $criteria, array $orderBy = null)
 * @method PieceJointe[]    findAll()
 * @method PieceJointe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PieceJointeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, PieceJointe::class);
    }


    public function findOneByFileName($filename)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT pj
           FROM App\Entity\PieceJointe pj
           WHERE pj.fileName = :filename"
		)->setParameter('filename', $filename);
		;
		return $query->getOneOrNullResult();
	}

	public function findOneByFileNameAndArrivageId($filename, $arrivageId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT pj
           FROM App\Entity\PieceJointe pj
           WHERE pj.fileName = :filename AND pj.arrivage = :arrivageId"
		)->setParameters(['filename' => $filename, 'arrivageId' => $arrivageId]);

		return $query->getOneOrNullResult();
	}
}