<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Fournisseur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Fournisseur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Fournisseur[]    findAll()
 * @method Fournisseur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Fournisseur::class);
    }

    public function findBySearch($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.nom like :value OR r.code_reference like :value')
            ->setParameter('value', '%' . $value . '%')
            ->orderBy('r.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function getNoOne($fournisseur)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT f
            FROM App\Entity\Fournisseur f
            WHERE f.id <> :fournisseur"
        )->setParameter('fournisseur', $fournisseur);;
        return $query->execute();
    }

    public function findOneByCodeReference($code)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
          FROM App\Entity\Fournisseur f
          WHERE f.codeReference LIKE :search"
        )->setParameter('search', $code);

        return $query->getOneOrNullResult();
    }

    public function getIdAndLibelleBySearch($search)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f.id, f.nom as text
          FROM App\Entity\Fournisseur f
          WHERE f.nom LIKE :search"
        )->setParameter('search', '%' . $search . '%');

        return $query->execute();
    }

    public function findByRefArticle($refArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT f
          FROM App\Entity\Fournisseur f
          WHERE f.refenceArticle = :refArticle
          "
        )->setParameter('refArticle', $refArticle);

        return $query->execute();
    }


    //    /**
    //     * @return Fournisseur[] Returns an array of Fournisseur objects
    //     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Fournisseur
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
