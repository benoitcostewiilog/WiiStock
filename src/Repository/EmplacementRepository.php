<?php

namespace App\Repository;

use App\Entity\Emplacement;
use Doctrine\ORM\EntityRepository;

/**
 * @method Emplacement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Emplacement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Emplacement[]    findAll()
 * @method Emplacement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmplacementRepository extends EntityRepository
{

    private const DtToDbLabels = [
        'name' => 'label',
        'deliveryPoint' => 'isDeliveryPoint',
        'ongoingVisibleOnMobile' => 'isOngoingVisibleOnMobile',
        'maxDelay' => 'dateMaxTime',
        'active' => 'isActive',
        'pairing' => 'pairing',
    ];

    public function getForSelect(?string $term, $deliveryType = null, $collectType = null) {
        $query = $this->createQueryBuilder("location");

        if($deliveryType) {
            $query->leftJoin("location.allowedDeliveryTypes", "allowed_delivery_types")
                ->andWhere("allowed_delivery_types.id = :type")
                ->setParameter("type", $deliveryType);
        }

        if($collectType) {
            $query->leftJoin("location.allowedCollectTypes", "allowed_collect_types")
                ->andWhere("allowed_collect_types.id = :type")
                ->setParameter("type", $collectType);
        }

        return $query->select("location.id AS id, location.label AS text")
            ->andWhere("location.label LIKE :term")
            ->setParameter("term", "%$term%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getLocationsArray()
    {
        return $this->createQueryBuilder('location')
            ->select('location.id')
            ->addSelect('location.label')
            ->where('location.isActive = true')
            ->getQuery()
            ->getResult();
    }

    public function countAll()
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('COUNT(location)');

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLabel($label, $emplacementId = null)
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('COUNT(location.label')
            ->where('location.label = :label');

		if ($emplacementId) {
            $qb->andWhere('location.id != :id');
		}

        $qb->setParameter('label', $label);

		if ($emplacementId) {
            $qb->setParameter('id', $emplacementId);
		}

        return $qb
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getIdAndLabelActiveBySearch($search)
    {
        $qb = $this->createQueryBuilder('location');

        $qb->select('location.id AS id')
            ->addSelect('location.label AS text')
            ->where('location.label LIKE :search')
            ->andWhere('location.isActive = 1')
            ->orderBy('location.label', 'ASC')
            ->setParameter('search', '%' . str_replace('_', '\_', $search) . '%');

        return $qb
            ->getQuery()
            ->execute();
    }

    public function getLocationsByType($type, $search, $restrictResults) {
        $qb = $this->createQueryBuilder('location');

        $qb->select('location.id AS id')
            ->addSelect('location.label AS text')
            ->andWhere('location.label LIKE :search')
            ->setParameter('search', '%' . str_replace('_', '\_', $search) . '%');

        if ($type) {
            $qb
                ->andWhere('(:type MEMBER OF location.allowedDeliveryTypes) OR (:type MEMBER OF location.allowedCollectTypes)')
                ->setParameter('type', $type);
        }

        if ($restrictResults) {
            $qb->setMaxResults(50);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByParamsAndExcludeInactive($params = null, $excludeInactive = false)
    {
        $countTotal = $this->countAll();

        $em = $this->getEntityManager();
        $qb = $em
            ->createQueryBuilder()
            ->from('App\Entity\Emplacement', 'e');

        if ($excludeInactive) {
            $qb->where('e.isActive = 1');
        }

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('e.label LIKE :value OR e.description LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                $field = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['name']];
                if (!empty($order) && $field) {
                    if($field === 'pairing') {
                        $qb->leftJoin('e.pairings', 'order_pairings')
                            ->leftJoin('e.locationGroup', 'order_locationGroup')
                            ->leftJoin('order_locationGroup.pairings', 'order_locationGroupPairings')
                            ->orderBy('IFNULL(order_pairings.active, order_locationGroupPairings.active)', $order);
                    } else if(property_exists(Emplacement::class, $field)) {
                        $qb->orderBy("e.${field}", $order);
                    }
                }
            }
            $qb->select('count(e)');
            $countQuery = (int) $qb->getQuery()->getSingleScalarResult();
        }
        else {
            $countQuery = $countTotal;
        }

        $qb
            ->select('e');
        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();
        return [
            'data' => $query ? $query->getResult() : null,
            'allEmplacementDataTable' => !empty($params) ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function findWhereArticleIs()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "
            SELECT e.id, e.label, (
                SELECT COUNT(m)
                FROM App\Entity\TrackingMovement AS m
                JOIN m.emplacement e_other
                JOIN m.type t
                WHERE e_other.label = e.label AND t.nom LIKE 'depose'
            ) AS nb
            FROM App\Entity\Emplacement AS e
            WHERE e.dateMaxTime IS NOT NULL AND e.dateMaxTime != ''
            ORDER BY nb DESC"
        );
        return $query->execute();
    }
}
