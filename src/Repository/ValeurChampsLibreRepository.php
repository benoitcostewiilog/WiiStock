<?php

namespace App\Repository;

use App\Entity\ValeurChampsLibre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ValeurChampsLibre|null find($id, $lockMode = null, $lockVersion = null)
 * @method ValeurChampsLibre|null findOneBy(array $criteria, array $orderBy = null)
 * @method ValeurChampsLibre[]    findAll()
 * @method ValeurChampsLibre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValeurChampsLibreRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ValeurChampsLibre::class);
    }

    public function getByArticleType($idArticle,$idType)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.id, v.valeur, c.label
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.articleReference a
            JOIN v.champLibre c
            JOIN c.type t
            WHERE a.id = :idArticle AND t.id = :idType"
            );
        $query->setParameters([
            "idArticle"=> $idArticle,
            "idType"=> $idType
        ]);

        return $query->execute();
    }
   
    public function getByRefArticleANDChampsLibre($idArticle,$idChampLibre)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.articleReference a
            JOIN v.champLibre c
            WHERE a.id = :idArticle AND c.id = :idChampLibre"
            );
        $query->setParameters([
            "idArticle"=> $idArticle,
            "idChampLibre"=> $idChampLibre
        ]);

        return $query->getSingleResult();
    }

    public function getByRefArticle($idArticle)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT v.valeur, c.label
            FROM App\Entity\ValeurChampsLibre v
            JOIN v.articleReference a
            JOIN v.champLibre c
            WHERE a.id = :idArticle "
            );
        $query->setParameter("idArticle", $idArticle);

        return $query->execute();
    }


    // /**
    //  * @return ValeurChampsLibre[] Returns an array of ValeurChampsLibre objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('v.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ValeurChampsLibre
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
