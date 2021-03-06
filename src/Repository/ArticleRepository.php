<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FreeField;
use App\Entity\IOT\Sensor;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;

use App\Helper\QueryCounter;
use WiiCommon\Helper\Stream;
use App\Service\VisibleColumnService;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use WiiCommon\Helper\StringHelper;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends EntityRepository {

    private const FIELD_ENTITY_NAME = [
        "location" => "emplacement",
        "unitPrice" => "prixUnitaire",
        "quantity" => "quantite"
    ];

    private const FIELDS_TYPE_DATE = [
        "dateLastInventory",
        "expiryDate",
        "stockEntryDate"
    ];

    public function findExpiredToGenerate($delay = 0) {
        $since = new DateTime("now");
        $since->modify("+{$delay}day");

        return $this->createQueryBuilder("a")
            ->join('a.statut','status')
            ->where("a.expiryDate <= :since")
            ->andWhere("status.code != :consumed")
            ->setParameter("since", $since)
            ->setParameter('consumed', Article::STATUT_INACTIF)
            ->getQuery()
            ->getResult();
    }

    public function getReferencesByRefAndDate($refPrefix, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT a.reference
            FROM App\Entity\Article a
            WHERE a.reference LIKE :refPrefix'
		)->setParameter('refPrefix', $refPrefix . $date . '%');

		return array_column($query->execute(), 'reference');
	}

    public function setNullByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            '
            UPDATE App\Entity\Article a
            SET a.receptionReferenceArticle = null
            WHERE a.receptionReferenceArticle = :id'
        )->setParameter('id', $id);
        return $query->execute();
    }

    public function findByCollecteId($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a
             FROM App\Entity\Article a
             JOIN a.collectes c
             WHERE c.id = :id
            "
        )->setParameter('id', $id);
        return $query->getResult();
    }

    /**
     * @param $demandes
     * @param false $needAssoc
     * @return Article[]
     */
    public function findByDemandes($demandes, $needAssoc = false)
    {
        $queryBuilder = $this->createQueryBuilder('article')
            ->select('article');

        if ($needAssoc) {
            $queryBuilder->addSelect('demande.id AS demandeId');
        }

        $result = $queryBuilder
            ->join('article.demande' , 'demande')
            ->where('article.demande IN (:demandes)')
            ->setParameter('demandes', $demandes)
            ->getQuery()
            ->execute();

        if ($needAssoc) {
            $result = array_reduce($result, function(array $carry, $current) {
                $article =  $current[0];
                $demandeId = $current['demandeId'];

                if (!isset($carry[$demandeId])) {
                    $carry[$demandeId] = [];
                }

                $carry[$demandeId][] = $article;
                return $carry;
            }, []);
        }
        return $result;
    }

    public function countByType($typeId)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            WHERE a.type = :typeId
           "
        )->setParameter('typeId', $typeId);

        return $query->getSingleScalarResult();
    }

    public function getArticleByReception($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT a.id as id, a.barCode as text
            FROM App\Entity\Article a
            JOIN a.reception r
            WHERE r.id = :id "
        )->setParameter('id', $id);;
        return $query->execute();
    }

    public function getIdRefLabelAndQuantity()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT a.id, a.reference, a.label, a.quantite, a.barCode
            FROM App\Entity\Article a
            "
        );
        return $query->execute();
    }

    public function iterateAll() {
        $iterator = $this->createQueryBuilder('article')
            ->select('referenceArticle.reference')
            ->addSelect('article.label')
            ->addSelect('article.quantite')
            ->addSelect('type.label as typeLabel')
            ->addSelect('statut.nom as statutName')
            ->addSelect('article.commentaire')
            ->addSelect('emplacement.label as empLabel')
            ->addSelect('article.barCode')
            ->addSelect('article.dateLastInventory')
            ->addSelect('article.freeFields')
            ->addSelect('article.batch')
            ->addSelect('article.stockEntryDate')
            ->addSelect('article.expiryDate')
            ->leftJoin('article.articleFournisseur', 'articleFournisseur')
            ->leftJoin('article.emplacement', 'emplacement')
            ->leftJoin('article.type', 'type')
            ->leftJoin('article.statut', 'statut')
            ->leftJoin('articleFournisseur.referenceArticle', 'referenceArticle')
            ->getQuery()
            ->iterate(null, Query::HYDRATE_ARRAY);

        foreach($iterator as $item) {
            // $item [index => article array]
            yield array_pop($item);
        }
    }

	public function getIdAndRefBySearch($search, $activeOnly = false, $field = 'reference', $referenceArticleReference = null, $activeReferenceOnly = false)
	{
        $statusNames = [
            Article::STATUT_ACTIF,
            Article::STATUT_EN_LITIGE
        ];

        $queryBuilder = $this->createQueryBuilder('article')
            ->select('article.id AS id')
            ->addSelect("article.${field} AS text")
            ->addSelect('location.label AS locationLabel')
            ->addSelect('article.quantite AS quantity')
            ->leftJoin('article.emplacement', 'location')
            ->where("article.${field} LIKE :search")
            ->setParameter('search', '%' . $search . '%');

        if ($activeOnly) {
            $queryBuilder
                ->join('article.statut', 'status');

            $exprBuilder = $queryBuilder->expr();
            $OROperands = [];
            foreach ($statusNames as $index => $statusName) {
                $OROperands[] = "status.nom = :articleStatusName$index";
                $queryBuilder->setParameter("articleStatusName$index", $statusName);
            }
            $queryBuilder->andWhere('(' . $exprBuilder->orX(...$OROperands) . ')');
        }

        if ($referenceArticleReference) {
            $queryBuilder
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->andWhere('referenceArticle.reference = :referenceArticleReference')
                ->setParameter('referenceArticleReference', $referenceArticleReference);
        }

        if ($activeReferenceOnly) {
            $queryBuilder
                ->join('article.articleFournisseur', 'activeReference_articleFournisseur')
                ->join('activeReference_articleFournisseur.referenceArticle', 'activeReference_referenceArticle')
                ->join('activeReference_referenceArticle.statut', 'activeReference_status')
                ->andWhere('activeReference_status.nom = :activeReference_statusName')
                ->setParameter('activeReference_statusName', ReferenceArticle::STATUT_ACTIF);
        }

		return $queryBuilder
            ->getQuery()
            ->execute();
	}

	public function findByRefArticleAndStatut($refArticle, array $statusNames, string $refArticleStatusName = null)
	{

		$queryBuilder = $this->createQueryBuilder('article')
            ->select('article')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->where('referenceArticle = :refArticle')
            ->setParameter('refArticle', $refArticle);

		if(!empty($statusNames)) {
            $queryBuilder->join('article.statut', 'article_status');
            $exprBuilder = $queryBuilder->expr();
            $OROperands = [];

            foreach ($statusNames as $index => $statusName) {
                $OROperands[] = "article_status.nom = :articleStatusName$index";
                $queryBuilder->setParameter("articleStatusName$index", $statusName);
            }
            $queryBuilder->andWhere('(' . $exprBuilder->orX(...$OROperands) . ')');
        }

		if ($refArticleStatusName) {
            $queryBuilder
                ->join('referenceArticle.statut', 'referenceArticle_status')
                ->andWhere('referenceArticle_status.nom = :referenceArticle_statusName')
                ->setParameter('referenceArticle_statusName', $refArticleStatusName);
        }

		return $queryBuilder->getQuery()->execute();
	}

    public function getTotalQuantiteFromRefNotInDemand($refArticle, $statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT SUM(a.quantite)
			FROM App\Entity\Article a
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE a.statut =:statut AND ra = :refArticle AND a.demande is null
			'
        )->setParameters([
            'refArticle' => $refArticle,
            'statut' => $statut
        ]);

        return $query->getSingleScalarResult();
    }

	public function findActifByRefArticleWithoutDemand($refArticle = null, $preparation = null, $demande = null)
	{
		return $this->createQueryBuilderActifWithoutDemand($refArticle, $preparation, $demande)
            ->getQuery()
            ->execute();
	}

	private function createQueryBuilderActifWithoutDemand($refArticle = null, $preparation = null, $demande = null): QueryBuilder
	{
	    $queryBuilder = $this->createQueryBuilder('article')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('article.statut', 'articleStatut')
            ->leftJoin('article.demande', 'demande')
            ->leftJoin('demande.statut', 'statutDemande')
            ->where('articleStatut.nom = :articleActif')
            ->andWhere('article.quantite IS NOT NULL')
            ->andWhere('article.quantite > 0')
            ->andWhere('(article.preparation IS NULL OR article.preparation = :prepa OR statutDemande.nom = :delivered)')
            ->andWhere('(article.demande IS NULL OR article.demande = :dem OR statutDemande.nom = :draft OR statutDemande.nom = :delivered)')
            ->setParameter('articleActif', Article::STATUT_ACTIF)
            ->setParameter('prepa', $preparation)
            ->setParameter('dem', $demande)
            ->setParameter('delivered', Demande::STATUT_LIVRE)
            ->setParameter('draft', Demande::STATUT_BROUILLON);

	    if (!empty($refArticle)) {
            $queryBuilder
                ->andWhere('referenceArticle = :refArticle')
                ->setParameter('refArticle', $refArticle);
        }

	    return $queryBuilder;

	}

    public function findByParamsAndFilters($params, $filters, $user)
    {
        $qb = $this->createQueryBuilder("a");

        $countQuery = $countTotal = QueryCounter::count($qb, 'a');

		// filtres sup
		foreach ($filters as $filter) {
			switch ($filter['field']) {
				case 'statut':
					$value = explode(',', $filter['value']);
					$qb
						->join('a.statut', 's_filter')
						->andWhere('s_filter.nom IN (:statut)')
						->setParameter('statut', $value);
					break;
			}
		}

		// prise en compte des paramètres issus du datatable
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $searchValue = $params->get('search')['value'];

                if (!empty($searchValue)) {
                    $search = "%$searchValue%";

                    $ids = [];
                    $query = [];

                    // valeur par défaut si aucune valeur enregistrée pour cet utilisateur
					$searchForArticle = $user->getRechercheForArticle();
					if (empty($searchForArticle)) {
						$searchForArticle = Utilisateur::SEARCH_DEFAULT;
					}

                    foreach ($searchForArticle as $key => $searchField) {

                        $date = DateTime::createFromFormat('d/m/Y', $searchValue);
                        $date = $date ? $date->format('Y-m-d') : null;
                        switch ($searchField) {
                            case "type":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.type', 't_search')
                                    ->andWhere('t_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;

                            case "status":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.statut', 's_search')
                                    ->andWhere('s_search.nom LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "location":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.emplacement', 'e_search')
                                    ->andWhere('e_search.label LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "articleReference":
                            case "reference":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.articleFournisseur', 'afa')
                                    ->leftJoin('afa.referenceArticle', 'ra')
                                    ->andWhere('ra.reference LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            case "supplierReference":
                                $subqb = $this->createQueryBuilder("a")
                                    ->select('a.id')
                                    ->leftJoin('a.articleFournisseur', 'afa')
                                    ->andWhere('afa.reference LIKE :search')
                                    ->setParameter('search', $search);

                                foreach ($subqb->getQuery()->execute() as $idArray) {
                                    $ids[] = $idArray['id'];
                                }
                                break;
                            default:
                                $field = self::FIELD_ENTITY_NAME[$searchField] ?? $searchField;
                                $freeFieldId = VisibleColumnService::extractFreeFieldId($field);
                                if(is_numeric($freeFieldId)) {
                                    $query[] = "JSON_SEARCH(a.freeFields, 'one', :search, NULL, '$.\"$freeFieldId\"') IS NOT NULL";
                                    $qb->setParameter("search", $date ?: $search);
                                } else if (property_exists(Article::class, $field)) {
                                    if ($date && in_array($field, self::FIELDS_TYPE_DATE)) {
                                        $query[] = "a.$field BETWEEN :dateMin AND :dateMax";
                                        $qb
                                            ->setParameter('dateMin' , $date . ' 00:00:00')
                                            ->setParameter('dateMax' , $date . ' 23:59:59');
                                    } else {
                                        $query[] = "a.$field LIKE :search";
                                        $qb->setParameter('search', $search);
                                    }
                                }
                                break;
                        }
                    }

                    // si le résultat de la recherche est vide on renvoie []
                    if (empty($ids)) {
                        $ids = [0];
                    }

                    foreach ($ids as $id) {
                        $query[] = 'a.id  = ' . $id;
                    }

                    if (!empty($query)) {
                        $qb->andWhere(implode(' OR ', $query));
                    }
                }

				$countQuery =  QueryCounter::count($qb, 'a');
			}

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                    switch ($column) {
                        case "type":
                            $qb->leftJoin('a.type', 't')
                                ->orderBy('t.label', $order);
                            break;
                        case "supplierReference":
                            $qb->leftJoin('a.articleFournisseur', 'af1')
                                ->orderBy('af1.reference', $order);
                            break;
                        case "location":
                            $qb->leftJoin('a.emplacement', 'e')
                                ->orderBy('e.label', $order);
                            break;
                        case "reference":
                            $qb->leftJoin('a.articleFournisseur', 'af2')
                                ->leftJoin('af2.referenceArticle', 'ra2')
                                ->orderBy('ra2.reference', $order);
                            break;
                        case "status":
                            $qb->leftJoin('a.statut', 's_sort')
                                ->orderBy('s_sort.nom', $order);
                            break;
                        case "pairing":
                            $qb->leftJoin('a.pairings', 'order_pairings')
                                ->orderBy('order_pairings.active', $order);
                            break;
                        default:
                            $field = self::FIELD_ENTITY_NAME[$column] ?? $column;
                            $freeFieldId = VisibleColumnService::extractFreeFieldId($column);

                            if(is_numeric($freeFieldId)) {
                                /** @var FreeField $freeField */
                                $freeField = $this->getEntityManager()->getRepository(FreeField::class)->find($freeFieldId);
                                if($freeField->getTypage() === FreeField::TYPE_NUMBER) {
                                    $qb->orderBy("CAST(JSON_EXTRACT(a.freeFields, '$.\"$freeFieldId\"') AS SIGNED)", $order);
                                } else {
                                    $qb->orderBy("JSON_EXTRACT(a.freeFields, '$.\"$freeFieldId\"')", $order);
                                }
                            } else if (property_exists(Article::class, $field)) {
                                $qb->orderBy("a.$field", $order);
                            }
                            break;
                    }
                }
            }

            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }

    public function countActiveArticles()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\Article a
            JOIN a.statut s
            WHERE s.nom = :active"
		)->setParameter('active', Article::STATUT_ACTIF);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(a)
            FROM App\Entity\Article a"
		);

        return $query->getSingleScalarResult();
    }

    public function findDoublons()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a1
			FROM App\Entity\Article a1
			WHERE a1.reference IN (
				SELECT a2.reference FROM App\Entity\Article a2
				GROUP BY a2.reference
				HAVING COUNT(a2.reference) > 1)"
        );

        return $query->execute();
    }

    public function getByPreparationsIds($preparationsIds)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT a.reference,
                         e.label as location,
                         a.label,
                         (CASE
                            WHEN a.quantiteAPrelever IS NULL THEN a.quantite
                            ELSE a.quantiteAPrelever
                         END) as quantity,
                         0 as is_ref,
                         p.id as id_prepa,
                         a.barCode,
                         ra.reference as reference_article_reference
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.preparation p
			JOIN p.statut s
			JOIN a.articleFournisseur af
			JOIN af.referenceArticle ra
			WHERE p.id IN (:preparationsIds)
			  AND a.quantite > 0"
        )->setParameter('preparationsIds', $preparationsIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

    public function getArticlePrepaForPickingByUser($user, array $preparationIdsFilter = []) {
        $queryBuilder = $this->createQueryBuilderActifWithoutDemand()
            ->select('DISTINCT article.reference AS reference')
            ->addSelect('article.label AS label')
            ->addSelect('emplacement.label AS location')
            ->addSelect('article.quantite AS quantity')
            ->addSelect('referenceArticle.reference AS reference_article')
            ->addSelect('article.barCode AS barCode')
            ->addSelect('referenceArticle.stockManagement AS management')
            ->addSelect("
                (CASE
                    WHEN (referenceArticle.stockManagement = :fefoStockManagement AND article.expiryDate IS NOT NULL) THEN DATE_FORMAT(article.expiryDate, '%d/%m/%Y')
                    WHEN (referenceArticle.stockManagement = :fifoStockManagement AND article.stockEntryDate IS NOT NULL) THEN DATE_FORMAT(article.stockEntryDate, '%d/%m/%Y %T')
                    ELSE :null
                END) AS management_date
            ")
            ->addSelect('
                (CASE
                    WHEN (referenceArticle.stockManagement = :fefoStockManagement AND article.expiryDate IS NOT NULL) THEN UNIX_TIMESTAMP(article.expiryDate)
                    WHEN (referenceArticle.stockManagement = :fifoStockManagement AND article.stockEntryDate IS NOT NULL) THEN UNIX_TIMESTAMP(article.stockEntryDate)
                    ELSE :null
                END) AS management_order
            ')
            ->leftJoin('article.emplacement', 'emplacement')
            ->join('referenceArticle.ligneArticlePreparations', 'ligneArticlePreparation')
            ->join('ligneArticlePreparation.preparation', 'preparation')
            ->join('preparation.statut', 'statutPreparation')
            ->andWhere('(statutPreparation.nom = :preparationToTreat OR (statutPreparation.nom = :preparationInProgress AND preparation.utilisateur = :preparationOperator))')
            ->setParameter('preparationToTreat', Preparation::STATUT_A_TRAITER)
            ->setParameter('preparationInProgress', Preparation::STATUT_EN_COURS_DE_PREPARATION)
            ->setParameter('preparationOperator', $user)
            ->setParameter('fifoStockManagement', ReferenceArticle::STOCK_MANAGEMENT_FIFO)
            ->setParameter('fefoStockManagement', ReferenceArticle::STOCK_MANAGEMENT_FEFO)
            ->setParameter('null', null);

        if (!empty($preparationIdsFilter)) {
            $queryBuilder
                ->andWhere('preparation.id IN (:preparationIdsFilter)')
                ->setParameter('preparationIdsFilter', $preparationIdsFilter, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    public function getByLivraisonsIds($livraisonsIds)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a.reference, e.label as location, a.label, a.quantitePrelevee as quantity, 0 as is_ref, l.id as id_livraison, a.barCode
			FROM App\Entity\Article a
			LEFT JOIN a.emplacement e
			JOIN a.preparation p
			JOIN p.livraison l
			JOIN l.statut s
			WHERE l.id IN (:livraisonsIds)
			  AND a.quantite > 0"
        )->setParameter('livraisonsIds', $livraisonsIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

	public function getByOrdreCollectesIds($collectesIds)
	{
		$em = $this->getEntityManager();
		//TODO patch temporaire CEA (sur quantité envoyée)
		$query = $em
			->createQuery($this->getArticleCollecteQuery() . " WHERE oc.id IN (:collectesIds)")
            ->setParameter('collectesIds', $collectesIds, Connection::PARAM_STR_ARRAY);

		return $query->execute();
	}

	public function getByTransferOrders(array $transfersOrders): array {
	    if (!empty($transfersOrders)) {
            $res = $this->createQueryBuilder('article')
                ->select('article.barCode AS barcode')
                ->addSelect('referenceArticle.libelle AS label')
                ->addSelect('referenceArticle.reference AS reference')
                ->addSelect('referenceArticle_location.label AS location')
                ->addSelect('article.quantite AS quantity')
                ->addSelect('transferOrder.id AS transfer_order_id')
                ->join('article.transferRequests', 'transferRequest')
                ->join('transferRequest.order', 'transferOrder')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->leftJoin('referenceArticle.emplacement', 'referenceArticle_location')
                ->where('transferOrder IN (:transferOrders)')
                ->setParameter('transferOrders', $transfersOrders)
                ->getQuery()
                ->getResult();
        }
	    else {
            $res = [];
        }
		return $res;
	}

	public function getByOrdreCollecteId($collecteId)
	{
		$em = $this->getEntityManager();
		$query = $em
			->createQuery($this->getArticleCollecteQuery() . " WHERE oc.id = :id")
			->setParameter('id', $collecteId);

		return $query->execute();
	}

    public function getByStatutAndTypeWithoutInProgressPrepaNorLivraison(string $statusName,
                                                                         string $typeLabel,
                                                                         array $statutsPrepa,
                                                                         array $statutsLivraison) {
        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();

        $queryBuilder = $queryBuilder
            ->join('article.type', 'type')
            ->join('article.statut', 'statut')
            ->leftJoin('article.preparation', 'preparation')
            ->leftJoin('preparation.statut', 'statutPreparation')
            ->leftJoin('preparation.livraison', 'livraison')
            ->leftJoin('livraison.statut', 'statutLivraison')
            ->where(
                $exprBuilder->andX(
                    $exprBuilder->eq('type.label', ':typeLabel'),
                    $exprBuilder->eq('statut.nom', ':statusName'),
                    $exprBuilder->orX(
                        $exprBuilder->isNull('preparation'),
                        $exprBuilder->notIn('statutPreparation.nom', $statutsPrepa)
                    ),
                    $exprBuilder->orX(
                        $exprBuilder->isNull('livraison'),
                        $exprBuilder->notIn('statutLivraison.nom', $statutsLivraison)
                    )
                )
            )
            ->setParameter('typeLabel', $typeLabel)
            ->setParameter('statusName', $statusName);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

	private function getArticleCollecteQuery()
	{
		return (/** @lang DQL */
		"SELECT ra.reference,
			 e.label as location,
			 a.label,
			 a.quantite as quantity,
			 0 as is_ref, oc.id as id_collecte,
			 a.barCode,
			 ra.libelle as reference_label
			FROM App\Entity\Article a
			JOIN a.articleFournisseur artf
			JOIN artf.referenceArticle ra
			LEFT JOIN a.emplacement e
			JOIN a.ordreCollecte oc
			LEFT JOIN oc.statut s"
		);
	}

    public function findOneByReference($reference)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->getOneOrNullResult();
	}

	public function findByReference($reference)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT a
			FROM App\Entity\Article a
			WHERE a.reference = :reference"
		)->setParameter('reference', $reference);

		return $query->execute();
	}

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
			FROM App\Entity\Article a
			JOIN a.emplacement e
			WHERE e.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    public function getEntryByMission($mission, $artId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT e.date, e.quantity
            FROM App\Entity\InventoryEntry e
            WHERE e.mission = :mission AND e.article = :art"
        )->setParameters([
            'mission' => $mission,
            'art' => $artId
        ]);
        return $query->getOneOrNullResult();
    }

    public function countByMission($mission)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(a)
            FROM App\Entity\InventoryMission im
            LEFT JOIN im.articles a
            WHERE im = :mission"
        )->setParameter('mission', $mission);

        return $query->getSingleScalarResult();
    }

    public function findActiveByFrequencyWithoutDateInventoryOrderedByEmplacementLimited($frequency, $limit)
    {

        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->select('article')
            ->join('article.articleFournisseur', 'articleFournisseur')
            ->join('articleFournisseur.referenceArticle', 'referenceArticle')
            ->join('referenceArticle.category', 'category')
            ->join('referenceArticle.statut', 'referenceArticle_status')
            ->leftJoin('article.statut', 'article_status')
            ->leftJoin('article.emplacement', 'article_location')
            ->where('category.frequency = :frequency')
            ->andWhere('referenceArticle.typeQuantite = :typeQuantity')
            ->andWhere('article.dateLastInventory IS NULL')
            ->andWhere('(' . $exprBuilder->orX('article_status.nom = :activeStatus', 'article_status.nom = :disputeStatus') . ')')
            ->andWhere('referenceArticle_status.nom = :referenceActiveStatus')
            ->orderBy('article_location.label')
            ->setParameters([
                'frequency' => $frequency,
                'typeQuantity' => ReferenceArticle::TYPE_QUANTITE_ARTICLE,
                'activeStatus' => Article::STATUT_ACTIF,
                'disputeStatus' => Article::STATUT_EN_LITIGE,
                'referenceActiveStatus' => ReferenceArticle::STATUT_ACTIF
            ]);

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

	public function getHighestBarCodeByDateCode($dateCode)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
		"SELECT a.barCode
		FROM App\Entity\Article a
		WHERE a.barCode LIKE :barCode
		ORDER BY a.barCode DESC
		")
            ->setParameter('barCode', Article::BARCODE_PREFIX . $dateCode . '%')
            ->setMaxResults(1);

        $result = $query->execute();
        return $result ? $result[0]['barCode'] : null;;
    }

    public function countInventoryAnomaliesByArt($article)
    {
        $em = $this->getEntityManager();

        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(ie)
			FROM App\Entity\InventoryEntry ie
			JOIN ie.article a
			WHERE ie.anomaly = 1 AND a.id = :artId
			")->setParameter('artId', $article->getId());

        return $query->getSingleScalarResult();
    }

	public function findArticleByBarCodeAndLocation(string $barCode, string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->addSelect('article');

        return $queryBuilder->getQuery()->execute();
    }

	public function getOneArticleByBarCodeAndLocation(string $barCode, string $location) {
        $queryBuilder = $this
            ->createQueryBuilderByBarCodeAndLocation($barCode, $location)
            ->select('article.barCode as barCode')
            ->select('article.id as id')
            ->addSelect('article.quantite as quantity')
            ->addSelect('referenceArticle_status.nom as reference_status')
            ->addSelect('0 as is_ref')
            ->join('article.articleFournisseur', 'article_articleFournisseur')
            ->join('article_articleFournisseur.referenceArticle', 'articleFournisseur_reference')
            ->join('articleFournisseur_reference.statut', 'referenceArticle_status');

        $result = $queryBuilder->getQuery()->execute();
        return !empty($result) ? $result[0] : null;
    }

    private function createQueryBuilderByBarCodeAndLocation(string $barCode, string $location): QueryBuilder {
        $queryBuilder = $this->createQueryBuilder('article');
        $exprBuilder = $queryBuilder->expr();
        $queryBuilder
            ->join('article.emplacement', 'emplacement')
            ->join('article.statut', 'status')
            ->andWhere('emplacement.label = :location')
            ->andWhere('article.barCode = :barCode')
            ->andWhere($exprBuilder->orX(
                'status.nom = :activeStatusName',
                'status.nom = :statusDisputeName'
            ))
            ->setParameter('location', $location)
            ->setParameter('barCode', $barCode)
            ->setParameter('activeStatusName', Article::STATUT_ACTIF)
            ->setParameter('statusDisputeName', Article::STATUT_EN_LITIGE);

        return $queryBuilder;
    }

    public function findActiveOrDisputeForReference($reference, Emplacement $emplacement) {
        return $this->createQueryBuilder("a")
            ->join("a.articleFournisseur", "af")
            ->leftJoin("a.statut", "articleStatut")
            ->where("articleStatut.nom IN (:statuses)")
            ->andWhere("af.referenceArticle = :reference")
            ->andWhere('a.emplacement = :location')
            ->setParameter("reference", $reference)
            ->setParameter("location", $emplacement)
            ->setParameter("statuses", [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])
            ->getQuery()
            ->getResult();
    }

    public function getArticlesGroupedByTransfer(array $requests, bool $isRequests = true) {
        if(!empty($requests)) {
            $queryBuilder = $this->createQueryBuilder('article')
                ->select('article.barCode AS barCode')
                ->addSelect('referenceArticle.reference AS reference')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->join('articleFournisseur.referenceArticle', 'referenceArticle')
                ->join('article.transferRequests', 'transferRequest');

            if ($isRequests) {
                $queryBuilder
                    ->addSelect('transferRequest.id AS transferId')
                    ->where('transferRequest.id IN (:requests)')
                    ->setParameter('requests', $requests);
            }
            else {
                $queryBuilder
                    ->addSelect('transferOrder.id AS transferId')
                    ->join('transferRequest.order', 'transferOrder')
                    ->where('transferOrder.id IN (:orders)')
                    ->setParameter('orders', $requests);
            }

            $res = $queryBuilder
                ->getQuery()
                ->getResult();

            return Stream::from($res)
                ->reduce(function (array $acc, array $articleArray) {
                    $transferRequestId = $articleArray['transferId'];
                    if (!isset($acc[$transferRequestId])) {
                        $acc[$transferRequestId] = [];
                    }
                    $acc[$transferRequestId][] = $articleArray;
                    return $acc;
                }, []);
        }
        else {
            return [];
        }
    }
    public function findWithNoPairing(?string $term) {
        return $this->createQueryBuilder("article")
            ->select("article.id AS id, article.barCode AS text")
            ->leftJoin("article.pairings", "pairings")
            ->where("pairings.article is null OR pairings.active = 0")
            ->andWhere("article.barCode LIKE :term")
            ->setParameter("term", "%$term%")
            ->setMaxResults(100)
            ->getQuery()
            ->getArrayResult();
    }

    private function createSensorPairingDataQueryUnion(Article $article): string {
        $createQueryBuilder = function () {
            return $this->createQueryBuilder('article')
                ->select('pairing.id AS pairingId')
                ->addSelect('sensorWrapper.name AS name')
                ->addSelect('(CASE WHEN sensorWrapper.deleted = false AND pairing.active = true AND (pairing.end IS NULL OR pairing.end > NOW()) THEN 1 ELSE 0 END) AS active')
                ->addSelect('article.barCode AS entity')
                ->addSelect("'" . Sensor::ARTICLE . "' AS entityType")
                ->addSelect('article.id AS entityId')
                ->join('article.pairings', 'pairing')
                ->join('pairing.sensorWrapper', 'sensorWrapper')
                ->where('article = :article');
        };

        $startQueryBuilder = $createQueryBuilder();
        $startQueryBuilder
            ->addSelect("pairing.start AS date")
            ->addSelect("'start' AS type")
            ->andWhere('pairing.start IS NOT NULL');

        $endQueryBuilder = $createQueryBuilder();
        $endQueryBuilder
            ->addSelect("pairing.end AS date")
            ->addSelect("'end' AS type")
            ->andWhere('pairing.end IS NOT NULL');

        $sqlAliases = [
            '/AS \w+_0/' => 'AS pairingId',
            '/AS \w+_1/' => 'AS name',
            '/AS \w+_2/' => 'AS active',
            '/AS \w+_3/' => 'AS entity',
            '/AS \w+_4/' => 'AS entityType',
            '/AS \w+_5/' => 'AS entityId',
            '/AS \w+_6/' => 'AS date',
            '/AS \w+_7/' => 'AS type',
            '/\?/' => $article->getId()
        ];

        $startSQL = $startQueryBuilder->getQuery()->getSQL();
        $startSQL = StringHelper::multiplePregReplace($sqlAliases, $startSQL);

        $endSQL = $endQueryBuilder->getQuery()->getSQL();
        $endSQL = StringHelper::multiplePregReplace($sqlAliases, $endSQL);

        $entityManager = $this->getEntityManager();
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $preparationArticleSQL = $preparationRepository->createArticleSensorPairingDataQueryUnion($article);

        $collectOrderRepository = $entityManager->getRepository(OrdreCollecte::class);
        $collectArticleSQL = $collectOrderRepository->createArticleSensorPairingDataQueryUnion($article);

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $locationSQL = $locationRepository->createArticleSensorPairingDataQueryUnion($article);

        return "
            ($startSQL)
            UNION
            ($endSQL)
            UNION
            $preparationArticleSQL
            UNION
            $collectArticleSQL
            UNION
            $locationSQL
        ";
    }

    public function getSensorPairingData(Article $article, int $start, int $count): array {
        $unionSQL = $this->createSensorPairingDataQueryUnion($article);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        /** @noinspection SqlResolve */
        return $connection
            ->executeQuery("
                SELECT *
                FROM ($unionSQL) AS pairing
                ORDER BY `date` DESC
                LIMIT $count OFFSET $start
            ")
            ->fetchAllAssociative();
    }

    public function countSensorPairingData(Article $article): int {
        $unionSQL = $this->createSensorPairingDataQueryUnion($article);

        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $unionQuery = $connection->executeQuery("
            SELECT COUNT(*) AS count
            FROM ($unionSQL) AS pairing
        ");
        $res = $unionQuery->fetchAllAssociative();
        return $res[0]['count'] ?? 0;
    }

    public function findArticlesOnLocation(Emplacement $location): array {
        return $this->createQueryBuilder('article')
            ->join('article.statut', 'status')
            ->where('status.code IN (:availableStatuses)')
            ->andWhere('article.emplacement = :location')
            ->setParameter('availableStatuses', [Article::STATUT_ACTIF, Article::STATUT_EN_LITIGE])
            ->setParameter('location', $location)
            ->getQuery()
            ->getResult();
    }
}
