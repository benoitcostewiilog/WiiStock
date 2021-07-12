<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\Notification;
use App\Helper\QueryCounter;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends EntityRepository {

    public function getByParams($params, $filters) {
        $qb = $this->createQueryBuilder("n")
            ->addOrderBy('n.triggered', 'DESC');

        $total = QueryCounter::count($qb, "n");
        $countFiltered = $total;
        foreach($filters as $filter) {
            switch ($filter['field']) {
                case 'dateMin':
                    $qb->andWhere('n.triggered >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00:00:00');
                    break;
                case 'dateMax':
                    $qb->andWhere('n.triggered <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
            }
        }

        // prise en compte des paramètres issus du datatable
        if(!empty($params)) {
            if(!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if(!empty($search)) {
                    $qb
                        ->andWhere($qb->expr()->orX(
                            'n.content LIKE :value',
                            'n.source LIKE :value',
                            "DATE_FORMAT(n.triggered, '%d/%m/%Y') LIKE :value",
                        ))
                        ->setParameter('value', '%' . str_replace('_', '\_', $search) . '%');
                }
            }

            $countFiltered = QueryCounter::count($qb, "n");
        }

        if(!empty($params)) {
            if(!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if(!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(AbstractQuery::HYDRATE_OBJECT),
            'count' => $countFiltered,
            'total' => $total
        ];
    }
}
