<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Device;
use App\Entity\IOT\Message;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends EntityRepository
{
    public function findByParams($params)
    {
        $qb = $this->createQueryBuilder('message')
            ->orderBy('message.date', 'DESC');

        $countFiltered = $countTotal = QueryCounter::count($qb, 'message');

        if ($params) {
            if (!empty($params->get('device'))) {
                $qb
                    ->where('message.device = :device')
                    ->setParameter('device', $params->get('device'));
            }
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
            $countFiltered = QueryCounter::count($qb, "message");
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
