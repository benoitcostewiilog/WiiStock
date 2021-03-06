<?php

namespace App\Repository;

use App\Entity\FiabilityByReference;
use Doctrine\ORM\EntityRepository;

/**
 * @method FiabilityByReference|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiabilityByReference|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiabilityByReference[]    findAll()
 * @method FiabilityByReference[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiabilityByReferenceRepository extends EntityRepository
{
}
