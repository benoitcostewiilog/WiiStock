<?php

namespace App\Repository;

use App\Entity\FieldsParam;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method FieldsParam|null find($id, $lockMode = null, $lockVersion = null)
 * @method FieldsParam|null findOneBy(array $criteria, array $orderBy = null)
 * @method FieldsParam[]    findAll()
 * @method FieldsParam[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FieldsParamRepository extends ServiceEntityRepository
{

    function getByEntity($entity) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
            "SELECT f.fieldCode, f.fieldLabel, f.mustToCreate, f.mustToModify
            FROM App\Entity\FieldsParam f
            WHERE f.entityCode = :entity"
        )->setParameter('entity', $entity);

        return $query->execute();
    }
}
