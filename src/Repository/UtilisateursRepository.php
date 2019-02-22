<?php

namespace App\Repository;

use App\Entity\Utilisateurs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Utilisateurs|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateurs|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateurs[]    findAll()
 * @method Utilisateurs[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateursRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Utilisateurs::class);
    }

    public function findCountEmail($email)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(u)
            FROM App\Entity\Utilisateurs u
            WHERE u.email = :email"
        )->setParameter('email', $email);
        ;
        return $query->execute(); 
    }

    
    public function findUserGetIdUser(){
        $entityManager = $this->getEntityManager();
        $query =$entityManager->createQuery(
            "SELECT u.id, u.username
            FROM App\Entity\Utilisateurs u
            "
        );
        return $query->execute(); 
    }


//   /**
//     * @return Utilisateurs[] Returns an array of Utilisateurs objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Utilisateurs
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
