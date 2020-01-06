<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Collecte;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;

use App\Repository\ArticleRepository;
use App\Repository\CollecteReferenceRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\MailerServerRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\OrdreCollecteReferenceRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\StatutRepository;

use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use DateTime;
use Exception;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;

class OrdreCollecteService
{
    public const COLLECTE_ALREADY_BEGUN = 'collecte-already-began';

	/**
	 * @var EntityManagerInterface
	 */
    private $entityManager;
	/**
	 * @var \Twig_Environment
	 */
	private $templating;
	/**
	 * @var StatutRepository
	 */
	private $statutRepository;
	/**
	 * @var MailerServerRepository
	 */
	private $mailerServerRepository;
	/**
	 * @var MailerService
	 */
	private $mailerService;
	/**
	 * @var CollecteReferenceRepository
	 */
	private $collecteReferenceRepository;

	/**
	 * @var OrdreCollecteReferenceRepository
	 */
	private $ordreCollecteReferenceRepository;

	/**
	 * @var OrdreCollecteRepository
	 */
	private $ordreCollecteRepository;

	/**
	 * @var ArticleRepository
	 */
	private $articleRepository;

	/**
	 * @var FiltreSupRepository
	 */
	private $filtreSupRepository;

	/**
	 * @var Utilisateur
	 */
	private $user;

	/**
	 * @var Router
	 */
	private $router;

	private $mouvementTracaRepository;
	private $mouvementStockService;

    public function __construct(RouterInterface $router,
    							TokenStorageInterface $tokenStorage,
    							FiltreSupRepository $filtreSupRepository,
    							OrdreCollecteRepository $ordreCollecteRepository,
    							ArticleRepository $articleRepository,
								OrdreCollecteReferenceRepository $ordreCollecteReferenceRepository,
								MailerServerRepository $mailerServerRepository,
                                CollecteReferenceRepository $collecteReferenceRepository,
                                MailerService $mailerService,
                                StatutRepository $statutRepository,
                                MouvementStockService $mouvementStockService,
                                MouvementTracaRepository $mouvementTracaRepository,
                                EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
	{
	    $this->mailerServerRepository = $mailerServerRepository;
		$this->templating = $templating;
		$this->entityManager = $entityManager;
		$this->statutRepository = $statutRepository;
		$this->mailerService = $mailerService;
		$this->collecteReferenceRepository = $collecteReferenceRepository;
		$this->ordreCollecteReferenceRepository = $ordreCollecteReferenceRepository;
		$this->articleRepository = $articleRepository;
		$this->ordreCollecteRepository = $ordreCollecteRepository;
		$this->filtreSupRepository = $filtreSupRepository;
		$this->user = $tokenStorage->getToken()->getUser();
		$this->router = $router;
		$this->mouvementTracaRepository = $mouvementTracaRepository;
		$this->mouvementStockService = $mouvementStockService;
	}

	public function setEntityManager(EntityManagerInterface $entityManager): self {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @param OrdreCollecte $ordreCollecte
     * @param Utilisateur $user
     * @param DateTime $date
     * @param Emplacement $depositLocation
     * @param array $mouvements
     * @param bool $fromNomade
     * @return OrdreCollecte|null
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws Exception
     */
    public function finishCollecte(OrdreCollecte $ordreCollecte,
                                   Utilisateur $user,
                                   DateTime $date,
                                   Emplacement $depositLocation,
                                   array $mouvements,
                                   bool $fromNomade = false)
	{
		$em = $this->entityManager;
		$demandeCollecte = $ordreCollecte->getDemandeCollecte();
		$dateNow = new DateTime('now', new DateTimeZone('Europe/Paris'));

		$listRefRef = $listArtRef = [];
		$referenceToQuantity = [];
		$artToQuantity = [];
		foreach($mouvements as $mouvement) {
		    $quantity = $mouvement['quantity'] ?? $mouvement['quantite'];
			if ($mouvement['is_ref']) {
				$listRefRef[] = $mouvement['reference'];
                $referenceToQuantity[$mouvement['reference']] = $quantity;
			} else {
				$listArtRef[] = $mouvement['reference'];
                $artToQuantity[$mouvement['reference']] = $quantity;
			}
		}

		// on construit la liste des lignes à transférer vers une nouvelle collecte
		$rowsToRemove = [];
		$listOrdreCollecteReference = $this->ordreCollecteReferenceRepository->findByOrdreCollecte($ordreCollecte);
		foreach ($listOrdreCollecteReference as $ordreCollecteReference) {
			$refArticle = $ordreCollecteReference->getReferenceArticle();
			if (!in_array($refArticle->getReference(), $listRefRef)) {
				$rowsToRemove[] = [
					'id' => $refArticle->getId(),
					'isRef' => 1
				];
			}
			else {
                $quantity = $referenceToQuantity[$refArticle->getReference()];
                $oldQuantity = $ordreCollecteReference->getQuantite();
                if($quantity > 0 && $quantity < $oldQuantity) {
                    $ordreCollecteReference->setQuantite($quantity);
                }
            }
		}

		$listArticles = $this->articleRepository->findByOrdreCollecteId($ordreCollecte->getId());
		foreach ($listArticles as $article) {
			if (!in_array($article->getReference(), $listArtRef)) {
				$rowsToRemove[] = [
					'id' => $article->getId(),
					'isRef' => 0
				];
			}
			else {
                $quantity = $artToQuantity[$article->getReference()];
                $oldQuantity = $article->getQuantite();
                if($quantity > 0 && $quantity < $oldQuantity) {
                    $article->setQuantite($quantity);
                }
            }
		}

		// cas de collecte partielle
		if (!empty($rowsToRemove)) {
			$newCollecte = new OrdreCollecte();
			$statutATraiter = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_COLLECTE, OrdreCollecte::STATUT_A_TRAITER);
			$newCollecte
				->setDate($ordreCollecte->getDate())
				->setNumero('C-' . $dateNow->format('YmdHis'))
				->setDemandeCollecte($ordreCollecte->getDemandeCollecte())
				->setStatut($statutATraiter);

			$em->persist($newCollecte);

			foreach ($rowsToRemove as $mouvement) {
				if ($mouvement['isRef'] == 1) {
					$ordreCollecteRef = $this->ordreCollecteReferenceRepository->findByOrdreCollecteAndRefId($ordreCollecte, $mouvement['id']);
					$ordreCollecte->removeOrdreCollecteReference($ordreCollecteRef);
					$newCollecte->addOrdreCollecteReference($ordreCollecteRef);
				} else {
					$article = $this->articleRepository->find($mouvement['id']);
					$ordreCollecte->removeArticle($article);
					$newCollecte->addArticle($article);
				}
			}

			$demandeCollecte->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_COLLECTE, Collecte::STATUT_INCOMPLETE));

			$em->flush();
		}
		else {
		// cas de collecte totale
			$demandeCollecte
				->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Collecte::CATEGORIE, Collecte::STATUT_COLLECTE))
				->setValidationDate($dateNow);
		}

		// on modifie le statut de l'ordre de collecte
		$ordreCollecte
			->setUtilisateur($user)
			->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_TRAITE))
			->setDate($date);

		// on modifie la quantité des articles de référence liés à la collecte
		$collecteReferences = $this->ordreCollecteReferenceRepository->findByOrdreCollecte($ordreCollecte);

		// cas de mise en stockage
		if ($demandeCollecte->getStockOrDestruct()) {
			foreach ($collecteReferences as $collecteReference) {
				$refArticle = $collecteReference->getReferenceArticle();
				$refArticle->setQuantiteStock($refArticle->getQuantiteStock() + $collecteReference->getQuantite());
                $this->persistMouvements(
                    $user,
                    $refArticle,
                    $date,
                    $demandeCollecte->getPointCollecte(),
                    $depositLocation,
                    $collecteReference->getQuantite(),
                    $fromNomade
                );
			}

			// on modifie le statut des articles liés à la collecte
			$articles = $ordreCollecte->getArticles();
			foreach ($articles as $article) {
				$article
                    ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_ACTIF))
                    ->setEmplacement($depositLocation);
                $this->persistMouvements(
                    $user,
                    $article,
                    $date,
                    $demandeCollecte->getPointCollecte(),
                    $depositLocation,
                    $article->getQuantite(),
                    $fromNomade
                );
			}
		}
		$this->entityManager->flush();

		$partialCollect = !empty($rowsToRemove);

		if ($this->mailerServerRepository->findAll()) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Collecte effectuée',
                $this->templating->render(
                    'mails/mailCollecteDone.html.twig',
                    [
                        'title' => $partialCollect ?
							'Votre demande de collecte a été partiellement effectuée.' :
							'Votre demande de collecte a bien été effectuée.',
                        'collecte' => $ordreCollecte,
						'demande' => $demandeCollecte,
                    ]
                ),
                $demandeCollecte->getDemandeur()->getEmail()
            );
        }

		return $newCollecte ?? null;
	}

    /**
     * @param null $params
     * @param null $demandeCollecteIdFilter
     * @return array
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
	public function getDataForDatatable($params = null, $demandeCollecteIdFilter = null)
	{
		if ($demandeCollecteIdFilter) {
			$filters = [
				['field' => 'demandeCollecte',
				'value' => $demandeCollecteIdFilter]
			];
		} else {
			$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_ORDRE_COLLECTE, $this->user);
		}
		$queryResult = $this->ordreCollecteRepository->findByParamsAndFilters($params, $filters);

		$collectes = $queryResult['data'];

		$rows = [];
		foreach ($collectes as $collecte) {
			$rows[] = $this->dataRowCollecte($collecte);
		}

		return [
			'data' => $rows,
			'recordsTotal' => $queryResult['total'],
			'recordsFiltered' => $queryResult['count'],
		];
	}

	/**
	 * @param OrdreCollecte $collecte
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
	private function dataRowCollecte($collecte)
	{
		$demandeCollecte = $collecte->getDemandeCollecte();

		$url['show'] = $this->router->generate('ordre_collecte_show', ['id' => $collecte->getId()]);
		return [
			'id' => $collecte->getId() ?? '',
			'Numéro' => $collecte->getNumero() ?? '',
			'Date' => $collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : '',
			'Statut' => $collecte->getStatut() ? $collecte->getStatut()->getNom() : '',
			'Opérateur' => $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '',
			'Type' => $demandeCollecte && $demandeCollecte->getType() ? $demandeCollecte->getType()->getLabel() : '',
			'Actions' => $this->templating->render('ordre_collecte/datatableCollecteRow.html.twig', [
				'url' => $url,
			])
		];
	}

    /**
     * @param Utilisateur $user
     * @param ReferenceArticle|Article $article
     * @param DateTime $date
     * @param Emplacement $locationFrom
     * @param Emplacement $locationTo
     * @param int $quantity
     * @param bool $fromNomade
     * @throws NonUniqueResultException
     */
    private function persistMouvements(Utilisateur $user,
                                       $article,
                                       DateTime $date,
                                       Emplacement $locationFrom,
                                       Emplacement $locationTo,
                                       int $quantity,
                                       bool $fromNomade = false): void {
        $mouvementStock = $this->mouvementStockService->createMouvementStock($user, $locationFrom, $quantity, $article, MouvementStock::TYPE_ENTREE);

        if ($fromNomade) {
            $mouvementTraca = new MouvementTraca();
            $typePrise = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MVT_TRACA, MouvementTraca::TYPE_PRISE);

            // TODO AB article en inactif ?

            $mouvementTraca
                ->setColis($article->getReference())
                ->setEmplacement($locationFrom)
                ->setOperateur($user)
                ->setUniqueIdForMobile($this->generateUniqueIdForMobile($date))
                ->setDatetime($date)
                ->setFinished(false)
                ->setType($typePrise)
                ->setMouvementStock($mouvementStock);
        }
        else {
            // TODO AB article en actif
            $this->mouvementStockService->finishMouvementStock($mouvementStock, $date, $locationTo);
        }

        $this->entityManager->persist($mouvementStock);

        if (isset($mouvementTraca)) {
            $this->entityManager->persist($mouvementTraca);
        }
    }

    private function generateUniqueIdForMobile(DateTime $date): string {
        $uniqueId = null;
        //same format as moment.defaultFormat
        $dateStr = $date->format('Y-m-dTH:i:sP');
        $randomLength = 9;
        do {
            $random = strtolower(substr(sha1(rand()), 0, $randomLength));
            $uniqueId = $dateStr . '_' . $random;
            $existingMouvements = $this->mouvementTracaRepository->findBy(['uniqueIdForMobile' => $uniqueId]);
        } while (count($existingMouvements) === 0);

        return $uniqueId;
    }
}
