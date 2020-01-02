<?php

namespace App\Repository;

use App\Entity\Livraison;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Exception;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Livraison|null find($id, $lockMode = null, $lockVersion = null)
 * @method Livraison|null findOneBy(array $criteria, array $orderBy = null)
 * @method Livraison[]    findAll()
 * @method Livraison[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LivraisonRepository extends ServiceEntityRepository
{
	const DtToDbLabels = [
		'Numéro' => 'numero',
		'Statut' => 'statut',
		'Date' => 'date',
		'Opérateur' => 'utilisateur',
		'Type' => 'type'
	];

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Livraison::class);
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(l)
            FROM App\Entity\Livraison l
            JOIN l.destination dest
            WHERE dest.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

	/**
	 * @param int $preparationId
	 * @return Livraison|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
    public function findOneByPreparationId($preparationId)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT l
			FROM App\Entity\Livraison l
			JOIN l.demande d
			JOIN d.preparation p
			WHERE p.id = :preparationId
			"
		)->setParameter('preparationId', $preparationId);

		return $query->getOneOrNullResult();
	}

	public function getByStatusLabelAndWithoutOtherUser($statusLabel, $user)
	{
        $typeUser = [];
        if ($user->getTypes()) {
            foreach ($user->getTypes() as $type) {
                $typeUser[] = $type->getId();
            }
        }
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT l.id,
                         l.numero as number,
                         dest.label as location
			FROM App\Entity\Livraison l
			JOIN l.statut s
			JOIN l.demande d
			JOIN d.destination dest
			JOIN d.type t
			WHERE (s.nom = :statusLabel AND (l.utilisateur is null or l.utilisateur = :user)) AND t.id IN (:type)"
		)->setParameters([
			'statusLabel' => $statusLabel,
			'user' => $user,
            'type' => $typeUser
		]);

		return $query->execute();
	}

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function countByUser($user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(l)
            FROM App\Entity\Livraison l
            WHERE l.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
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
			->select('l')
			->from('App\Entity\Livraison', 'l');

		$countTotal = count($qb->getQuery()->getResult());

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$qb
						->leftJoin('l.statut', 's')
						->andWhere('s.nom = :statut')
						->setParameter('statut', $filter['value']);
					break;
				case 'type':
					$qb
						->join('l.demande', 'd')
						->leftJoin('d.type', 't')
						->andWhere('t.label = :type')
						->setParameter('type', $filter['value']);
					break;
				case 'utilisateurs':
					$value = explode(',', $filter['value']);
					$qb
						->join('l.utilisateur', 'u')
						->andWhere("u.id in (:userId)")
						->setParameter('userId', $value);
					break;
				case 'dateMin':
					$qb
						->andWhere('l.date >= :dateMin')
						->setParameter('dateMin', $filter['value'] . " 00:00:00");
					break;
				case 'dateMax':
					$qb
						->andWhere('l.date <= :dateMax')
						->setParameter('dateMax', $filter['value'] . " 23:59:59");
					break;
			}
		}

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('l.statut', 's2')
						->leftJoin('l.utilisateur', 'u2')
						->leftJoin('l.demande', 'd2')
						->leftJoin('d2.type', 't2')
						->andWhere('
						l.numero LIKE :value
						OR s2.nom LIKE :value
						OR u2.username LIKE :value
						OR t2.label LIKE :value
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

					if ($column === 'statut') {
						$qb
							->leftJoin('l.statut', 's3')
							->orderBy('s3.nom', $order);
					} else if ($column === 'utilisateur') {
						$qb
							->leftJoin('l.utilisateur', 'u3')
							->orderBy('u3.username', $order);
					} else if ($column === 'type') {
						$qb
							->leftJoin('l.demande', 'd3')
							->leftJoin('d3.type', 't3')
							->orderBy('t3.label', $order);
					} else {
						$qb
							->orderBy('l.' . $column, $order);
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
			'data' => $query ? $query->getResult() : null ,
			'count' => $countFiltered,
			'total' => $countTotal
		];
	}
}
