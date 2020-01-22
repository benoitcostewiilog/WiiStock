<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\LigneArticle;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\DemandeRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\PreparationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * Class PreparationsManagerService
 * @package App\Service
 */
class PreparationsManagerService {

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';
    public const ARTICLE_ALREADY_SELECTED = 'article-already-selected';

    private $entityManager;
    private $articleDataService;

    /**
     * @var array
     */
    private $refMouvementsToRemove;

	/**
	 * @var Twig_Environment
	 */
	private $templating;

	/**
	 * @var RouterInterface
	 */
	private $router;

	/**
	 * @var PreparationRepository
	 */
	private $preparationRepository;

	/**
	 * @var Security
	 */
	private $security;

	/**
	 * @var FiltreSupRepository
	 */
	private $filtreSupRepository;

	/**
	 * @var DemandeRepository
	 */
	private $demandeRepository;

    public function __construct(DemandeRepository $demandeRepository,
								FiltreSupRepository $filtreSupRepository,
								Security $security,
								PreparationRepository $preparationRepository,
								RouterInterface $router,
								Twig_Environment $templating,
								ArticleDataService $articleDataService,
                                EntityManagerInterface $entityManager) {
    	$this->demandeRepository = $demandeRepository;
    	$this->filtreSupRepository = $filtreSupRepository;
    	$this->security = $security;
    	$this->preparationRepository = $preparationRepository;
    	$this->router = $router;
    	$this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->articleDataService = $articleDataService;
        $this->refMouvementsToRemove = [];
    }

    public function setEntityManager(EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * On termine les mouvements de prepa
     * @param Preparation $preparation
     * @param DateTime $date
     * @param Emplacement $emplacement
     */
    public function closePreparationMouvement(Preparation $preparation, DateTime $date, Emplacement $emplacement = null): void {
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $mouvements = $mouvementRepository->findByPreparation($preparation);

        foreach ($mouvements as $mouvement) {
            $mouvement->setDate($date);
            if (isset($emplacement)) {
                $mouvement->setEmplacementTo($emplacement);
            }
        }
    }

	/**
	 * @param Preparation $preparation
	 * @param Livraison $livraison
	 * @param $userNomade
	 * @throws NonUniqueResultException
	 */
    public function treatPreparation(Preparation $preparation, Livraison $livraison, $userNomade): void {
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $demandes = $preparation->getDemandes();
        $demande = $demandes[0];

        $livraison->addDemande($demande);

		$isPreparationComplete = $this->isPreparationComplete($demande);
		$prepaStatusLabel = $isPreparationComplete ? Preparation::STATUT_PREPARE : Preparation::STATUT_INCOMPLETE;
		$statutPreparePreparation = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, $prepaStatusLabel);
		$demandeStatusLabel = $isPreparationComplete ? Demande::STATUT_PREPARE : Demande::STATUT_INCOMPLETE;
		$statutPrepareDemande = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, $demandeStatusLabel);

        $preparation
            ->addLivraison($livraison)
            ->setUtilisateur($userNomade)
            ->setStatut($statutPreparePreparation);

        $demande->setStatut($statutPrepareDemande);
    }

    private function isPreparationComplete(Demande $demande)
	{
		$complete = true;

		$articles = $demande->getArticles();
		foreach ($articles as $article) {
			if ($article->getQuantitePrelevee() < $article->getQuantiteAPrelever()) $complete = false;
		}

		$lignesArticle = $demande->getLigneArticle();
		foreach ($lignesArticle as $ligneArticle) {
			if ($ligneArticle->getQuantitePrelevee() < $ligneArticle->getQuantite()) $complete = false;
		}

		return $complete;
	}

	/**
	 * @param DateTime $dateEnd
	 * @return Livraison
	 * @throws NonUniqueResultException
	 */
    public function persistLivraison(DateTime $dateEnd) {
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $statut = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_A_TRAITER);
        $livraison = new Livraison();

        $livraison
            ->setDate($dateEnd)
            ->setNumero('L-' . $dateEnd->format('YmdHis'))
            ->setStatut($statut);

        $this->entityManager->persist($livraison);

        return $livraison;
    }

	/**
	 * @param int $quantity
	 * @param Utilisateur $userNomade
	 * @param Livraison $livraison
	 * @param Emplacement|null $emplacementFrom
	 * @param bool $isRef
	 * @param string $article
	 * @param Preparation $preparation
	 * @param bool $isSelectedByArticle
	 * @throws NonUniqueResultException
	 */
    public function createMouvementLivraison(int $quantity,
											 Utilisateur $userNomade,
											 Livraison $livraison,
											 Emplacement $emplacementFrom = null,
											 bool $isRef,
											 $article,
											 Preparation $preparation,
											 bool $isSelectedByArticle) {
		$referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
		$articleRepository = $this->entityManager->getRepository(Article::class);
		$mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);

		$mouvement = new MouvementStock();
        $mouvement
            ->setUser($userNomade)
            ->setQuantity($quantity)
            ->setType(MouvementStock::TYPE_SORTIE)
            ->setLivraisonOrder($livraison);

        if (isset($emplacementFrom)) {
            $mouvement->setEmplacementFrom($emplacementFrom);
        }

        $this->entityManager->persist($mouvement);

		if ($isRef) {
			$refArticle = ($article instanceof ReferenceArticle)
				? $article
				: $referenceArticleRepository->findOneByReference($article);
			if ($refArticle) {
				$mouvement
					->setRefArticle($refArticle)
					->setQuantity($mouvementRepository->findOneByRefAndPrepa($refArticle->getId(), $preparation->getId())->getQuantity());
			}
		}
		else {
			$article = ($article instanceof Article)
				? $article
				: $articleRepository->findOneByReference($article);
			if ($article) {
				// si c'est un article sélectionné par l'utilisateur :
				// on prend la quantité donnée dans le mouvement
				// sinon on prend la quantité spécifiée dans le mouvement de transfert (créé dans beginPrepa)
				$mouvementQuantity = ($isSelectedByArticle
					? $quantity
					: $mouvementRepository->findByArtAndPrepa($article->getId(), $preparation->getId())->getQuantity());

				$mouvement
					->setArticle($article)
					->setQuantity($mouvementQuantity);
			}
		}
    }

    public function deleteLigneRefOrNot(?LigneArticle $ligne) {
        if ($ligne && $ligne->getQuantite() === 0) {
            $this->entityManager->remove($ligne);
        }
    }

	/**
	 * @param array $mouvement
	 * @param Preparation $preparation
	 * @throws Exception
	 */
    public function treatMouvementQuantities($mouvement, Preparation $preparation)
	{
		$referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
		$ligneArticleRepository = $this->entityManager->getRepository(LigneArticle::class);
		$articleRepository = $this->entityManager->getRepository(Article::class);
		$statutRepository = $this->entityManager->getRepository(Statut::class);

		if ($mouvement['is_ref']) {
			// cas ref par ref
			$refArticle = $referenceArticleRepository->findOneByReference($mouvement['reference']);
			if ($refArticle) {
				$ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $preparation->getDemandes()[0]);
				$ligneArticle->setQuantitePrelevee($mouvement['quantity']);
			}
		}
		else {
			// cas article
            /**
             * @var Article article
             */
			$article = $articleRepository->findOneByReference($mouvement['reference']);

			if ($article) {
                // cas ref par article
                if (isset($mouvement['selected_by_article']) && $mouvement['selected_by_article']) {
                    if ($article->getDemande()) {
                        throw new Exception(self::ARTICLE_ALREADY_SELECTED);
                    } else {
                        $demande = $preparation->getDemandes()[0];
                        $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                        $ligneArticle = $ligneArticleRepository->findOneByRefArticleAndDemande($refArticle, $demande);
                        $this->treatArticleSplitting($article, $mouvement['quantity'], $ligneArticle);
                        // et si ça n'a pas déjà été fait, on supprime le lien entre la réf article et la demande
                    }
                }

				$article
					->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_EN_TRANSIT))
					->setQuantitePrelevee($mouvement['quantity']);
			}
		}

		$this->entityManager->flush();
	}

	public function treatArticleSplitting(Article $article, int $quantite, LigneArticle $ligneArticle) {
        if ($quantite !== '' && $quantite > 0 && $quantite <= $article->getQuantite()) {
            if (!$article->getDemande()) {
                $article->setQuantiteAPrelever(0);
                $article->setQuantitePrelevee(0);
            }
            $article->setDemande($ligneArticle->getDemande());
            if ($quantite <= $article->getQuantitePrelevee()) {
                $ligneArticle->setQuantite($ligneArticle->getQuantite() + ($article->getQuantitePrelevee() - $quantite));
            } else {
                $ligneArticle->setQuantite($ligneArticle->getQuantite() - ($quantite - $article->getQuantitePrelevee()));
            }
            $article->setQuantiteAPrelever($quantite);
            $article->setQuantitePrelevee($quantite);
        }
    }

    /**
     * On supprime les mouvements de transfert créés pour les réf gérées à l'articles
     * (elles ont été remplacées plus haut par les mouvements de transfert des articles)
     */
    public function removeRefMouvements(): void {
        foreach ($this->refMouvementsToRemove as $mvtToRemove){
            $this->entityManager->remove($mvtToRemove);
        }
        $this->refMouvementsToRemove = [];
    }

	/**
	 * @param Preparation $preparation
	 * @param Utilisateur $user
	 * @throws NonUniqueResultException
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
    public function createMouvementsPrepaAndSplit(Preparation $preparation, Utilisateur $user) {
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);
        $statutRepository = $this->entityManager->getRepository(Statut::class);

        $demandes = $preparation->getDemandes();
        $demande = $demandes[0];

        // modification des articles de la demande
        $articles = $demande->getArticles();
        foreach ($articles as $article) {
            $mouvementAlreadySaved = $mouvementRepository->findByArtAndPrepa($article->getId(), $preparation->getId());
            if (!$mouvementAlreadySaved) {
                $quantitePrelevee = $article->getQuantitePrelevee();
                $selected = !(empty($quantitePrelevee));
                $article->setStatut(
                    $statutRepository->findOneByCategorieNameAndStatutName(
                        Article::CATEGORIE,
                        $selected ? Article::STATUT_EN_TRANSIT : Article::STATUT_ACTIF));
                // scission des articles dont la quantité prélevée n'est pas totale
                if ($article->getQuantite() !== $quantitePrelevee) {
                    $newArticle = [
                        'articleFournisseur' => $article->getArticleFournisseur()->getId(),
                        'libelle' => $article->getLabel(),
                        'prix' => $article->getPrixUnitaire(),
                        'conform' => !$article->getConform(),
                        'commentaire' => $article->getcommentaire(),
                        'quantite' => $selected ? $article->getQuantite() - $article->getQuantitePrelevee() : 0,
                        'emplacement' => $article->getEmplacement() ? $article->getEmplacement()->getId() : '',
                        'statut' => $selected ? Article::STATUT_ACTIF : Article::STATUT_INACTIF,
                        'refArticle' => $article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getId() : ''
                    ];

                    foreach ($article->getValeurChampsLibres() as $valeurChampLibre) {
                        $newArticle[$valeurChampLibre->getChampLibre()->getId()] = $valeurChampLibre->getValeur();
                    }
                    $insertedArticle = $this->articleDataService->newArticle($newArticle);
                    if ($selected) {
                        $article->setQuantite($quantitePrelevee);
                    } else {
                        $demande->addArticle($insertedArticle);
                        $demande->removeArticle($article);
                    }
                }

                if ($selected) {
                    // création des mouvements de préparation pour les articles
                    $mouvement = new MouvementStock();
                    $mouvement
                        ->setUser($user)
                        ->setArticle($article)
                        ->setQuantity($quantitePrelevee)
                        ->setEmplacementFrom($article->getEmplacement())
                        ->setType(MouvementStock::TYPE_TRANSFERT)
                        ->setPreparationOrder($preparation);
                    $this->entityManager->persist($mouvement);
                }
                $this->entityManager->flush();
            }
        }

        // création des mouvements de préparation pour les articles de référence
        foreach ($demande->getLigneArticle() as $ligneArticle) {
            $articleRef = $ligneArticle->getReference();

            $mouvementAlreadySaved = $mouvementRepository->findOneByRefAndPrepa($articleRef->getId(), $preparation->getId());
            if (!$mouvementAlreadySaved && !empty($ligneArticle->getQuantitePrelevee())) {
                $mouvement = new MouvementStock();
                $mouvement
                    ->setUser($user)
                    ->setRefArticle($articleRef)
                    ->setQuantity($ligneArticle->getQuantitePrelevee())
                    ->setEmplacementFrom($articleRef->getEmplacement())
                    ->setType(MouvementStock::TYPE_TRANSFERT)
                    ->setPreparationOrder($preparation);
                $this->entityManager->persist($mouvement);
                $this->entityManager->flush();
            }
        }

        if (!$preparation->getStatut() || !$preparation->getUtilisateur()) {
            // modif du statut de la préparation
            $statutEDP = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::PREPARATION, Preparation::STATUT_EN_COURS_DE_PREPARATION);
            $preparation
                ->setStatut($statutEDP)
                ->setUtilisateur($user);
            $this->entityManager->flush();
        }
    }

	/**
	 * @param array|null $params
	 * @return array
	 * @throws Exception
	 */
	public function getDataForDatatable($params = null)
	{
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PREPA, $this->security->getUser());

		$queryResult = $this->preparationRepository->findByParamsAndFilters($params, $filters);

		$preparations = $queryResult['data'];

		$rows = [];
		foreach ($preparations as $preparation) {
			$rows[] = $this->dataRowPreparation($preparation);
		}

		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
	}

	/**
	 * @param Preparation $preparation
	 * @return array
	 * @throws Twig_Error_Loader
	 * @throws Twig_Error_Runtime
	 * @throws Twig_Error_Syntax
	 */
	private function dataRowPreparation($preparation)
	{
		$demande = $this->demandeRepository->findOneByPreparation($preparation);
		$url['show'] = $this->router->generate('preparation_show', ['id' => $preparation->getId()]);
		$row = [
			'Numéro' => $preparation->getNumero() ?? '',
			'Date' => $preparation->getDate() ? $preparation->getDate()->format('d/m/Y') : '',
			'Opérateur' => $preparation->getUtilisateur() ? $preparation->getUtilisateur()->getUsername() : '',
			'Statut' => $preparation->getStatut() ? $preparation->getStatut()->getNom() : '',
			'Type' => $demande && $demande->getType() ? $demande->getType()->getLabel() : '',
			'Actions' => $this->templating->render('preparation/datatablePreparationRow.html.twig', ['url' => $url]),
		];

		return $row;
	}

}
