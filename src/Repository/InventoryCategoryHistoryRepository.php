<?php

namespace App\Repository;

use App\Entity\InventoryCategoryHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method InventoryCategoryHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method InventoryCategoryHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method InventoryCategoryHistory[]    findAll()
 * @method InventoryCategoryHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InventoryCategoryHistoryRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, InventoryCategoryHistory::class);
    }
}