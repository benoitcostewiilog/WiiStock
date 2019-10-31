<?php

namespace App\Repository;

use App\Entity\Statut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Statut|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statut|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statut[]    findAll()
 * @method Statut[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatutRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Statut::class);
    }

    public function findByCategorieName($categorieName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
            FROM App\Entity\Statut s
            JOIN s.categorie c
            WHERE c.nom = :categorieName"
        );

        $query->setParameter("categorieName", $categorieName);

        return $query->execute();
    }

    public function findByName($name)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
            FROM App\Entity\Statuts s
            WHERE s.nom = :name"
        );
        $query->setParameter("name", $name);

        return $query->execute();
    }


    /**
     * @param string $categorieName
     * @param string $statutName
     * @return Statut | null
     * @throws NonUniqueResultException
     */
    public function findOneByCategorieNameAndStatutName($categorieName, $statutName)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT s
          FROM App\Entity\Statut s
          JOIN s.categorie c
          WHERE c.nom = :categorieName AND s.nom = :statutName
          "
        );

        $query->setParameters([
            'categorieName' => $categorieName,
            'statutName' => $statutName
        ]);

        return $query->getOneOrNullResult();
    }
}
