<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Urgence;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends ServiceEntityRepository
{

    private const DtToDbLabels = [
        "start" => 'dateStart',
        "end" => 'dateEnd',
        'commande' => 'commande'
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Urgence::class);
    }

    /**
     * @param Arrivage $arrivage
     * @return Urgence|null
     */
    public function findUrgenceMatching(Arrivage $arrivage): ?Urgence {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT u
                FROM App\Entity\Urgence u
                WHERE u.dateStart <= :date
                  AND u.dateEnd >= :date
                  AND u.commande LIKE :commande"
        )->setParameters([
            'date' => $arrivage->getDate(),
            'commande' => $arrivage->getNumeroBL()
        ]);

        $res = $query->getResult();

        return count($res) > 0 ? $res[0] : null;
    }

    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('u')
            ->from('App\Entity\Urgence', 'u');

        $countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'commande':
					$qb->andWhere('u.commande = :commande')
						->setParameter('commande', $filter['value']);
					break;
				case 'dateMin':
					$qb->andWhere('u.dateEnd >= :dateMin')
						->setParameter('dateMin', $filter['value'] . " 00:00:00");
					break;
				case 'dateMax':
					$qb->andWhere('u.dateStart <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

        dump('----------------------------------------------');
        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('u.commande LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                dump($params->get('order'));
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    dump($column);
                    dump($order);
                    $qb
                        ->orderBy('u.' . $column, $order);
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
