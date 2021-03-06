<?php

namespace App\Repository;

use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Exception;
use Generator;

/**
 * @method MouvementStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method MouvementStock|null findOneBy(array $criteria, array $orderBy = null)
 * @method MouvementStock[]    findAll()
 * @method MouvementStock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MouvementStockRepository extends EntityRepository
{
    private const DtToDbLabels = [
        'date' => 'date',
        'refArticle' => 'refArticle',
        'quantite' => 'quantity',
        'origine' => 'emplacementFrom',
        'destination' => 'emplacementTo',
        'type' => 'type',
        'operateur' => 'user',
        'barCode' => 'barCode'
    ];

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            JOIN m.emplacementFrom ef
            JOIN m.emplacementTo et
            WHERE ef.id = :emplacementId OR et.id =:emplacementId"
        )->setParameter('emplacementId', $emplacementId);
        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m"
        );
        return $query->getSingleScalarResult();
    }

    /**
     * @param Preparation $preparation
     * @return MouvementStock[]
     */
    public function findByPreparation($preparation)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.preparationOrder = :preparation"
        )->setParameter('preparation', $preparation);

        return $query->execute();
    }

    /**
     * @param Livraison $livraison
     * @return MouvementStock[]
     */
    public function findByLivraison($livraison)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.livraisonOrder = :livraison"
        )->setParameter('livraison', $livraison);

        return $query->execute();
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Generator
     */
    public function iterateByDates(DateTime $dateMin, DateTime $dateMax): Generator
    {
        $dateMax = $dateMax->format('Y-m-d H:i:s');
        $dateMin = $dateMin->format('Y-m-d H:i:s');

        $iterator = $this->createQueryBuilder('mouvementStock')
            ->select('mouvementStock.date as date')
            ->addSelect('preparation.numero as preparationOrder')
            ->addSelect('livraison.numero as livraisonOrder')
            ->addSelect('collecte.numero as collectOrder')
            ->addSelect('reception.orderNumber as receptionOrder')
            ->addSelect('article.barCode as articleBarCode')
            ->addSelect('(CASE WHEN refArticle.id IS NOT NULL THEN refArticle.reference ELSE article_referenceArticle.reference END) as refArticleRef')
            ->addSelect('(CASE WHEN refArticle.id IS NOT NULL THEN refArticle.barCode ELSE article_referenceArticle.barCode END) as refArticleBarCode')
            ->addSelect('mouvementStock.quantity as quantity')
            ->addSelect('emplacementFrom.label as originEmpl')
            ->addSelect('destination.label as destinationEmpl')
            ->addSelect('mouvementStock.type as type')
            ->addSelect('user.username as operator')
            ->leftJoin('mouvementStock.preparationOrder','preparation')
            ->leftJoin('mouvementStock.livraisonOrder','livraison')
            ->leftJoin('mouvementStock.collecteOrder','collecte')
            ->leftJoin('mouvementStock.receptionOrder','reception')
            ->leftJoin('mouvementStock.article','article')
            ->leftJoin('mouvementStock.refArticle','refArticle')
            ->leftJoin('article.articleFournisseur','article_articleFournisseur')
            ->leftJoin('article_articleFournisseur.referenceArticle','article_referenceArticle')
            ->leftJoin('mouvementStock.emplacementFrom','emplacementFrom')
            ->leftJoin('mouvementStock.emplacementTo','destination')
            ->leftJoin('mouvementStock.user','user')
            ->where('mouvementStock.date BETWEEN :dateMin AND :dateMax')
            ->setParameter('dateMin' , $dateMin)
            ->setParameter('dateMax' , $dateMax)
            ->getQuery()
            ->iterate(null, Query::HYDRATE_ARRAY);

        foreach($iterator as $item) {
            // $item [index => movement]
            yield array_pop($item);
        }

    }

    /**
     * @param string[] $types
     * @return int
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function countByTypes($types, $dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();

        $dql = "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            WHERE m.type IN (:types)";


        if (!empty($dateDebut)) {
            $dql .= " AND m.date > :dateDebut";
        }

        if (!empty($dateFin)) {
            $dql .= " AND m.date < :dateFin";
        }
        $query = $em->createQuery(
            $dql
        );

        $query->setParameter('types', $types, Connection::PARAM_STR_ARRAY);
        if (!empty($dateDebut)) {
            $query->setParameter('dateDebut', $dateDebut);
        }

        if (!empty($dateFin)) {
            $query->setParameter('dateFin', $dateFin);
        }


        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\MouvementStock m
            WHERE m.type
            IN (:types)"
        )->setParameter('types', $types);
        return $query->getSingleScalarResult();
    }

    public function countTotalEntryPriceRefArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * ra.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.refArticle', 'ra');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv')
                ->setParameter('entreeInv', MouvementStock::TYPE_INVENTAIRE_ENTREE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv AND m.date > :dateDebut')
                ->setParameters([
                    'entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut
                ]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :entreeInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters([
                    'entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin
                ]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalExitPriceRefArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * ra.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.refArticle', 'ra');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv')
                ->setParameter('sortieInv', MouvementStock::TYPE_INVENTAIRE_SORTIE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv AND m.date > :dateDebut')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :sortieInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalEntryPriceArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * a.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.article', 'a');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv')
                ->setParameter('entreeInv', MouvementStock::TYPE_INVENTAIRE_ENTREE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :entreeInv AND m.date > :dateDebut')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :entreeInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['entreeInv' => MouvementStock::TYPE_INVENTAIRE_ENTREE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }

    public function countTotalExitPriceArticle($dateDebut = '', $dateFin = '')
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('SUM(m.quantity * a.prixUnitaire)')
            ->from('App\Entity\MouvementStock', 'm')
            ->join('m.article', 'a');

        if ($dateDebut == '' && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv')
                ->setParameter('sortieInv', MouvementStock::TYPE_INVENTAIRE_SORTIE);
        } else if (!empty($dateDebut) && $dateFin == '') {
            $qb
                ->where('m.type = :sortieInv AND m.date > :dateDebut')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut]);
        } else if (!empty($dateDebut) && !empty($dateFin)) {
            $qb
                ->where('m.type = :sortieInv AND m.date BETWEEN :dateDebut AND :dateFin')
                ->setParameters(['sortieInv' => MouvementStock::TYPE_INVENTAIRE_SORTIE,
                    'dateDebut' => $dateDebut,
                    'dateFin' => $dateFin]);
        }
        $query = $qb->getQuery();
        return $query->getSingleScalarResult();
    }


    /**
     * @param ReferenceArticle $referenceArticle
     * @return MouvementStock[]
     */
    public function findByRef(ReferenceArticle $referenceArticle)
    {
        $queryBuilder = $this->createQueryBuilder('mouvementStock');

        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $queryBuilder->andWhere('mouvementStock.refArticle = :refArticle');
        }
        else if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
            $queryBuilder
                ->join('mouvementStock.article', 'article')
                ->join('article.articleFournisseur', 'articleFournisseur')
                ->andWhere('articleFournisseur.referenceArticle = :refArticle');
        }

        $queryBuilder->setParameter('refArticle', $referenceArticle);

        return $queryBuilder
            ->getQuery()
            ->execute();
    }

    /**
     * @param $idArt
     * @param $idPrep
     * @return MouvementStock | null
     * @throws NonUniqueResultException
     */
    public function findByArtAndPrepa($idArt, $idPrep)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT m
            FROM App\Entity\MouvementStock m
            WHERE m.article = :id AND m.preparationOrder = :idP"
        )->setParameters([
            'id' => $idArt,
            'idP' => $idPrep
        ]);

        return $query->getOneOrNullResult();
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
            ->from('App\Entity\MouvementStock', 'm');

        $countTotal = $this->countAll();
        // filtres sup
        foreach ($filters as $filter) {
            switch ($filter['field']) {
                case 'statut':
                    $types = explode(',', $filter['value']);
                    $typeIds = array_map(function ($type) {
                        $splitted = explode(':', $type);
                        return $splitted[1] ?? $type;
                    }, $types);
                    $qb
                        ->andWhere('m.type in (:typeIds)')
                        ->setParameter('typeIds', $typeIds, Connection::PARAM_STR_ARRAY);
                    break;
                case 'emplacement':
                    $value = explode(':', $filter['value']);
                    $qb
                        ->leftJoin('m.emplacementFrom', 'ef')
                        ->leftJoin('m.emplacementTo', 'et')
                        ->andWhere('ef.label = :location OR et.label = :location')
                        ->setParameter('location', $value[1] ?? $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.user', 'u')
                        ->andWhere("u.id in (:userId)")
                        ->setParameter('userId', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('m.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value'] . " 00:00:00");
                    break;
                case 'dateMax':
                    $qb->andWhere('m.date <= :dateMax')
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
                        ->leftJoin('m.refArticle', 'ra3')
                        ->leftJoin('m.article', 'a3')
                        ->leftJoin('a3.articleFournisseur', 'af3')
                        ->leftJoin('af3.referenceArticle', 'ra4')
                        ->leftJoin('m.emplacementFrom', 'ef3')
                        ->leftJoin('m.emplacementTo', 'et3')
                        ->leftJoin('m.user', 'u3')
                        ->andWhere("(
						ra3.reference LIKE :value OR
						ra4.reference LIKE :value OR
						ef3.label LIKE :value OR
						ra3.barCode LIKE :value OR
						a3.barCode LIKE :value OR
						et3.label LIKE :value OR
						m.type LIKE :value OR
						u3.username LIKE :value OR
						DATE_FORMAT(m.date, '%d/%m/%Y') LIKE :value
						)")
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];

                    if ($column === 'refArticle') {
                        $qb
                            ->leftJoin('m.refArticle', 'ra2')
                            ->orderBy('ra2.reference', $order);
                    } else if ($column === 'emplacementFrom') {
                        $qb
                            ->leftJoin('m.emplacementFrom', 'ef2')
                            ->orderBy('ef2.label', $order);
                    } else if ($column === 'emplacementTo') {
                        $qb
                            ->leftJoin('m.emplacementTo', 'et2')
                            ->orderBy('et2.label', $order);
                    } else if ($column === 'user') {
                        $qb
                            ->leftJoin('m.user', 'u2')
                            ->orderBy('u2.username', $order);
                    } else if ($column === 'barCode') {
                        $qb

                            ->leftJoin('m.article','articleSort')
                            ->leftJoin('m.refArticle', 'raSort')
                            ->addOrderBy('raSort.barCode', $order)
                            ->addOrderBy('articleSort.barCode', $order);
                    } else {
                        $qb
                            ->orderBy('m.' . $column, $order);
                    }
                }
            }
        }
        $qb
            ->select('count(m)');
        // compte éléments filtrés
        $countFiltered = $qb->getQuery()->getSingleScalarResult();
        $qb
            ->select('m');
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
