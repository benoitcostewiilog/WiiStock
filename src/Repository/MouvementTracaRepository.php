<?php

namespace App\Repository;

use App\Entity\Emplacement;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Exception;


/**
 * @method MouvementTraca|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementTraca|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementTraca[]    findAll()
 * @method MouvementTraca[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementTracaRepository extends ServiceEntityRepository
{

    public const MOUVEMENT_TRACA_DEFAULT = 'tracking';
    public const MOUVEMENT_TRACA_STOCK = 'stock';

	private const DtToDbLabels = [
		'date' => 'datetime',
		'colis' => 'colis',
		'location' => 'emplacement',
		'type' => 'status',
		'operateur' => 'user',
	];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementTraca::class);
    }

    /**
     * @param $uniqueId
     * @return MouvementTraca
     * @throws NonUniqueResultException
     */
    public function findOneByUniqueIdForMobile($uniqueId) {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        	/** @lang DQL */
			'SELECT mvt
                FROM App\Entity\MouvementTraca mvt
                WHERE mvt.uniqueIdForMobile = :uniqueId'
        )->setParameter('uniqueId', $uniqueId);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param DateTime $dateMin
	 * @param DateTime $dateMax
	 * @return MouvementTraca[]
	 * @throws Exception
	 */
    public function findByDates($dateMin, $dateMax)
    {
		$dateMax = $dateMax->format('Y-m-d H:i:s');
		$dateMin = $dateMin->format('Y-m-d H:i:s');

        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            'SELECT m
            FROM App\Entity\MouvementTraca m
            WHERE m.datetime BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

	/**
	 * @param string $colis
	 * @return  MouvementTraca
	 */
    public function getLastByColis($colis)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT mt
			FROM App\Entity\MouvementTraca mt
			WHERE mt.colis = :colis
			ORDER BY mt.datetime DESC"
		)->setParameter('colis', $colis);

		$result = $query->execute();
		return $result ? $result[0] : null;
	}

	/**
	 * @param string $colis
	 * @param DateTimeInterface $date
	 * @return  MouvementTraca
	 */
    public function getByColisAndPriorToDate($colis, $date)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT mt
			FROM App\Entity\MouvementTraca mt
			WHERE mt.colis = :colis AND mt.datetime >= :date"
        )->setParameters([
            'colis' => $colis,
            'date' => $date,
        ]);

        $result = $query->execute();
        return $result;
    }

    /**
     * @param Emplacement $location
	 * @param string|null $natureIds
     * @return MouvementTraca[]
     * @throws DBALException
     */
    public function findObjectOnLocation(Emplacement $location, $natureIds = null): array {

        $finalQuery = $this->createQueryBuilderObjectOnLocation($location);

    	if ($natureIds) {
			$query = $this->getEntityManager()->createQuery(
				/** @lang DQL */
				'SELECT mt.id
				FROM App\Entity\MouvementTraca mt
				JOIN App\Entity\Colis c WITH mt.colis = c.code
				WHERE c.nature IN (:naturesId)
				AND mt.emplacement = :locationId
				')
				->setParameter('naturesId', $natureIds, Connection::PARAM_STR_ARRAY)
				->setParameter('locationId', $location->getId());

			$mvtTracaIds = array_column($query->execute(), 'id');

			$finalQuery
				->andWhere('mouvementTraca.id IN (:mouvementTracaIds)')
				->setParameter('mouvementTracaIds', $mvtTracaIds, Connection::PARAM_STR_ARRAY);
		}

    	return $finalQuery
			->getQuery()
            ->getResult();
    }

    /**
     * @param Emplacement $location
     * @return QueryBuilder
     * @throws DBALException
     */
    private function createQueryBuilderObjectOnLocation(Emplacement $location): QueryBuilder {
        $ids = $this->getIdForPacksOnLocations([$location]);
        return $this
            ->createQueryBuilder('mouvementTraca')
            ->where('mouvementTraca.id IN (:mouvementTracaIds)')
            ->setParameter('mouvementTracaIds', $ids, Connection::PARAM_STR_ARRAY);
    }

    public function getColisById(array $ids) {
        $result = $this
            ->createQueryBuilder('mouvementTraca')
            ->select('mouvementTraca.colis')
            ->andWhere('mouvementTraca.id IN (:mouvementTracaIds)')
            ->setParameter('mouvementTracaIds', $ids, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getResult();
        return $result ? array_column($result, 'colis') : [];
    }

    /**
     * Retourne les ids de moumvementTraca qui correspondent aux colis encours sur les emplacement donnés
     * @param Emplacement[]|int[] $locations
     * @param array $onDateBracket ['minDate' => DateTime, 'maxDate' => DateTime]
     * @return int[]
     * @throws DBALException
     */
    public function getIdForPacksOnLocations(array $locations, array $onDateBracket = []): array {
        $connection = $this->getEntityManager()->getConnection();

        return $connection
            ->executeQuery($this->createSQLQueryPacksOnLocation($locations, 'id', $onDateBracket), [])
            ->fetchAll(FetchMode::COLUMN);
    }

    /**
     * On retourne les ids des mouvementTraca qui correspondent à l'arrivée d'un colis (étant sur le/les emplacement)
     * sur un emplacement / groupement d'emplacement
     * (premières prises ou déposes sur l'emplacement ou le groupement d'emplacement où est présent le colis)
     * @param Emplacement[]|int[] $locations
     * @param array $onDateBracket ['minDate' => DateTime, 'maxDate' => DateTime]
     * @return int[]
     * @throws DBALException
     */
    public function getFirstIdForPacksOnLocations(array $locations, array $onDateBracket = []): array {
        $connection = $this->getEntityManager()->getConnection();

        $locationIds = $this->getIdsFromLocations($locations);
        $queryColisOnLocations = $this->createSQLQueryPacksOnLocation($locationIds, 'colis', $onDateBracket);
        $locationIdsStr = implode(',', $locationIds);
        $sqlQuery = "
              SELECT MIN(unique_packs.id) AS id
              FROM mouvement_traca AS unique_packs
              INNER JOIN (
                  SELECT mouvement_traca.colis         AS colis,
                         MIN(mouvement_traca.datetime) AS datetime
                  FROM mouvement_traca
                  WHERE mouvement_traca.emplacement_id IN (${locationIdsStr})
                    AND mouvement_traca.colis          IN (${$queryColisOnLocations})
                  GROUP BY mouvement_traca.colis, mouvement_traca.datetime
              ) AS min_datetime_packs ON min_datetime_packs.colis = unique_packs.colis
                                     AND min_datetime_packs.datetime = unique_packs.datetime
              GROUP BY unique_packs.colis
        ";

        return $connection
            ->executeQuery($sqlQuery, [])
            ->fetchAll(FetchMode::COLUMN);
    }

    /**
     * Retourne une chaîne SQL qui sélectionne les ids de moumvementTraca qui correspondent aux colis encours sur les emplacement donnés
     * @param array $locations
     * @param string $field
     * @param array $onDateBracket ['minDate' => DateTime, 'maxDate' => DateTime]
     * @return string
     */
    private function createSQLQueryPacksOnLocation(array $locations, string $field = 'id', array $onDateBracket = []): string {
        $locationIds = implode(',', $this->getIdsFromLocations($locations));
        $dropType = str_replace('\'', '\'\'', MouvementTraca::TYPE_DEPOSE);

        if (!empty($onDateBracket)
            && isset($onDateBracket['minDate'])
            && isset($onDateBracket['maxDate'])) {
            $minDate = $onDateBracket['minDate']->format('Y-m-d H:i:s');
            $maxDate = $onDateBracket['maxDate']->format('Y-m-d H:i:s');
            $locationsInDateBracketClause = "WHERE max_datetime_packs_local.emplacement_id IN (${$locationIds})";
            $uniquePackInLocationClause = "AND unique_packs_in_location.datetime BETWEEN '${minDate}' AND '${maxDate}'";
        }
        else {
            $locationsInDateBracketClause = '';
            $uniquePackInLocationClause = "AND unique_packs_in_location.emplacement_id IN (${$locationIds})";
        }

        return "
            SELECT unique_packs_in_location.${field}
            FROM mouvement_traca AS unique_packs_in_location
            INNER JOIN type on unique_packs_in_location.type_id = type.id
                   AND type.label = '${$dropType}'
            WHERE unique_packs_in_location.id IN (
                    SELECT MAX(unique_packs.id) AS id
                    FROM mouvement_traca AS unique_packs
                    INNER JOIN (
                        SELECT max_datetime_packs_local.colis         AS colis,
                               MAX(max_datetime_packs_local.datetime) AS datetime
                        FROM mouvement_traca max_datetime_packs_local
                        ${locationsInDateBracketClause}
                        GROUP BY max_datetime_packs_local.colis) max_datetime_packs ON max_datetime_packs.colis = unique_packs.colis
                                                                                   AND max_datetime_packs.datetime = unique_packs.datetime
                    GROUP BY unique_packs.colis
              )
              ${uniquePackInLocationClause}
        ";
    }

    /**
     * Return list of id form array of location
     * @param Emplacement[]|int[] $locations
     * @return array
     */
    private function getIdsFromLocations(array $locations): array {
        return array_map(
            function($location) {
                return ($location instanceof Emplacement)
                    ? $location->getId()
                    : $location;
            },
            $locations
        );
    }

    /**
     * @param array|null $params
     * @param array|null $filters
     * @return array
     * @throws Exception
     */
    public function findByParamsAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('m')
            ->from('App\Entity\MouvementTraca', 'm');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('m.type', 's')
						->andWhere('s.id in (:statut)')
						->setParameter('statut', $value);
					break;
                case 'emplacement':
                    $emplacementValue = explode(':', $filter['value']);
                    $qb
                        ->join('m.emplacement', 'e')
                        ->andWhere('e.label = :location')
                        ->setParameter('location', $emplacementValue[1] ?? $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.operateur', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'dateMin':
                    $qb
                        ->andWhere('m.datetime >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb
                        ->andWhere('m.datetime <= :dateMax')
                        ->setParameter('dateMax', $filter['value'] . " 23:59:59");
                    break;
                case 'colis':
                    $qb
                        ->andWhere('m.colis LIKE :colis')
                        ->setParameter('colis', '%' . $filter['value'] . '%');
                    break;
            }
        }

        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->leftJoin('m.emplacement', 'e2')
                        ->leftJoin('m.operateur', 'u2')
                        ->leftJoin('m.type', 's2')
                        ->andWhere('
						m.colis LIKE :value OR
						e2.label LIKE :value OR
						s2.nom LIKE :value OR
						u2.username LIKE :value
						')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order')))
            {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order))
                {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

                    if ($column === 'emplacement') {
                        $qb
                            ->leftJoin('m.emplacement', 'e3')
                            ->orderBy('e3.label', $order);
                    } else if ($column === 'status') {
                        $qb
                            ->leftJoin('m.type', 's3')
                            ->orderBy('s3.nom', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('m.operateur', 'u3')
                            ->orderBy('u3.username', $order);
                    } else {
                        $qb
                            ->orderBy('m.' . $column, $order);
                    }

                    $orderId = ($column === 'datetime')
                        ? $order
                        : 'DESC';
                    $qb->addOrderBy('m.id', $orderId);
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
            'data' => $query ? $query->getResult() : null ,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }

    /**
     * @param Utilisateur $operator
     * @param string $type self::MOUVEMENT_TRACA_STOCK | self::MOUVEMENT_TRACA_DEFAULT
     * @param array $filterDemandeCollecteIds
     * @return MouvementTraca[]
     */
    public function getTakingByOperatorAndNotDeposed(Utilisateur $operator,
                                                     string $type,
                                                     array $filterDemandeCollecteIds = []) {
        $typeCondition = ($type === self::MOUVEMENT_TRACA_STOCK)
            ? 'mouvementTraca.mouvementStock IS NOT NULL'
            : 'mouvementTraca.mouvementStock IS NULL'; // MOUVEMENT_TRACA_DEFAULT

        $queryBuilder = $this->createQueryBuilder('mouvementTraca')
            ->select('mouvementTraca.colis AS ref_article')
            ->addSelect('mouvementTracaType.nom AS type')
            ->addSelect('operator.username AS operateur')
            ->addSelect('location.label AS ref_emplacement')
            ->addSelect('mouvementTraca.uniqueIdForMobile AS date')
            ->addSelect('(CASE WHEN mouvementTraca.finished = 1 THEN 1 ELSE 0 END) AS finished')
            ->addSelect('(CASE WHEN mouvementTraca.mouvementStock IS NOT NULL THEN 1 ELSE 0 END) AS fromStock')
            ->join('mouvementTraca.type', 'mouvementTracaType')
            ->join('mouvementTraca.operateur', 'operator')
            ->join('mouvementTraca.emplacement', 'location')
            ->join('mouvementTraca.mouvementStock', 'mouvementStock')
            ->where('operator = :operator')
            ->andWhere('mouvementTracaType.nom LIKE :priseType')
            ->andWhere('mouvementTraca.finished = :finished')
            ->andWhere($typeCondition)
            ->setParameter('operator', $operator)
            ->setParameter('priseType', MouvementTraca::TYPE_PRISE)
            ->setParameter('finished', false);

        if (!empty($filterDemandeCollecteIds)) {
            $queryBuilder
                ->join('mouvementStock.collecteOrder', 'collecteOrder')
                ->andWhere('collecteOrder.id IN (:collecteOrderId)')
                ->setParameter('collecteOrderId', $filterDemandeCollecteIds, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

	public function countByEmplacement($emplacementId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            JOIN m.emplacement e
            WHERE e.id = :emplacementId"
		)->setParameter('emplacementId', $emplacementId);
		return $query->getSingleScalarResult();
	}

	/**
	 * @param MouvementStock $mouvementStock
	 * @return int
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function countByMouvementStock($mouvementStock)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(m)
            FROM App\Entity\MouvementTraca m
            WHERE m.mouvementStock = :mouvementStock"
		)->setParameter('mouvementStock', $mouvementStock);
		return $query->getSingleScalarResult();
	}
}
