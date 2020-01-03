<?php

namespace App\Repository;

use App\Entity\Acheminements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Acheminements|null find($id, $lockMode = null, $lockVersion = null)
 * @method Acheminements|null findOneBy(array $criteria, array $orderBy = null)
 * @method Acheminements[]    findAll()
 * @method Acheminements[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AcheminementsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Acheminements::class);
    }
    private const DtToDbLabels = [
        'Date' => 'date',
        'Demandeur' => 'requester',
        'Destinataire' => 'receiver',
        'Emplacement prise' => 'locationTake',
        'Emplacement de dépose' => 'locationDrop',
        'Nb Colis' => 'colis',
        'Statut' => 'statut',
    ];

    // /**
    //  * @return Acheminements[] Returns an array of Acheminements objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Acheminements
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findByParamAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('a')
            ->from('App\Entity\Acheminements', 'a');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $qb
                        ->join('a.statut', 's')
                        ->andWhere('s.nom = :statut')
                        ->setParameter('statut', $filter['value']);
                    break;
                case 'dateMin':
                    $qb->andWhere('a.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . ' 00.00.00');
                    break;
                case 'dateMax':
                    $qb->andWhere('a.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . ' 23:59:59');
                    break;
            }
        }
        if (!empty($params)) {
        if (!empty($params->get('search'))) {
            $search = $params->get('search')['value'];
            if (!empty($search)) {
                $qb
                    ->andWhere('a.colis LIKE :value OR a.date LIKE :value')
                    ->setParameter('value', '%' . $search . '%');
            }
        }
        if (!empty($params->get('order')))
        {
            $order = $params->get('order')[0]['dir'];
            if (!empty($order))
            {
                $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                if ($column === 'statut') {
                    $qb
                        ->leftJoin('a.statut', 's2')
                        ->orderBy('s2.nom', $order);
                } else if ($column === 'requester') {
                    $qb
                        ->leftJoin('a.requester', 'u2')
                        ->orderBy('u2.username', $order);
                } else if ($column === 'receiver') {
                    $qb
                        ->leftJoin('a.receiver', 'u2')
                        ->orderBy('u2.username', $order);
                } else {
                    $qb
                        ->orderBy('a.' . $column, $order);
                }
            }
        }
    }

        // compte éléments filtrés
        $countFiltered = count($qb->getQuery()->getResult());

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
