<?php

namespace App\Repository;

use App\Entity\InventoryFrequency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventoryFrequency|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryFrequency|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryFrequency[]    findAll()
 * @method InventoryFrequency[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryFrequencyRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryFrequency::class);
    }

    /**
     * @param string $label
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countByLabel($label)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
            "SELECT count(ic)
            FROM App\Entity\InventoryFrequency ic
            WHERE ic.label = :label"
        )->setParameter('label', $label);

        return $query->getSingleScalarResult();
    }

	/**
	 * @return InventoryFrequency[]
	 */
	public function findUsedByCat()
	{
		$em = $this->getEntityManager();

		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT if
			FROM App\Entity\InventoryFrequency if
			JOIN if.categories c"
		);

		return $query->execute();
	}

	public function countByLabelDiff($label, $frequencyLabel)
	{
		$em = $this->getEntityManager();

		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT count(if)
            FROM App\Entity\InventoryFrequency if
            WHERE if.label = :label AND if.label != :frequencyLabel"
		)->setParameters([
			'label' => $label,
			'frequencyLabel' => $frequencyLabel
		]);

		return $query->getSingleScalarResult();
	}
}