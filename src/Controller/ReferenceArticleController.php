<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\FiltreRef;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Entity\CollecteReference;
use App\Entity\CategorieCL;
use App\Entity\Fournisseur;
use App\Entity\Collecte;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FiltreRefRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\ArticleRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;

use App\Service\CSVExportService;
use App\Service\GlobalParamService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\SpecificService;
use App\Service\UserService;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use App\Entity\Demande;
use App\Entity\ArticleFournisseur;
use App\Repository\FournisseurRepository;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;


/**
 * @Route("/reference-article")
 */
class ReferenceArticleController extends AbstractController
{
    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;
    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var FiltreRefRepository
     */
    private $filtreRefRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var articleDataService
     */
    private $articleDataService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var Twig_Environment
     */
    private $templating;

	/**
	 * @var SpecificService
	 */
    private $specificService;

	/**
	 * @var ParametreRepository
	 */
    private $parametreRepository;

	/**
	 * @var ParametreRoleRepository
	 */
    private $parametreRoleRepository;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var InventoryCategoryRepository
     */
    private $inventoryCategoryRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var object|string
     */
    private $user;

    private $CSVExportService;

    public function __construct(TokenStorageInterface $tokenStorage,
                                GlobalParamService $globalParamService,
                                ParametreRoleRepository $parametreRoleRepository,
                                ParametreRepository $parametreRepository,
                                SpecificService $specificService,
                                Twig_Environment $templating,
                                EmplacementRepository $emplacementRepository,
                                FournisseurRepository $fournisseurRepository,
                                CategorieCLRepository $categorieCLRepository,
                                LigneArticleRepository $ligneArticleRepository,
                                ArticleRepository $articleRepository,
                                ArticleDataService $articleDataService,
                                LivraisonRepository $livraisonRepository,
                                DemandeRepository $demandeRepository,
                                CollecteRepository $collecteRepository,
                                ValeurChampLibreRepository $valeurChampLibreRepository,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ChampLibreRepository $champsLibreRepository,
                                ArticleFournisseurRepository $articleFournisseurRepository,
                                FiltreRefRepository $filtreRefRepository,
                                RefArticleDataService $refArticleDataService,
                                UserService $userService,
                                InventoryCategoryRepository $inventoryCategoryRepository,
                                InventoryFrequencyRepository $inventoryFrequencyRepository,
                                MouvementStockRepository $mouvementStockRepository,
                                CSVExportService $CSVExportService)
    {
        $this->emplacementRepository = $emplacementRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->champLibreRepository = $champsLibreRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->collecteRepository = $collecteRepository;
        $this->demandeRepository = $demandeRepository;
        $this->filtreRefRepository = $filtreRefRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->articleRepository = $articleRepository;
        $this->userService = $userService;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->templating = $templating;
        $this->specificService = $specificService;
        $this->parametreRepository = $parametreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->globalParamService = $globalParamService;
        $this->inventoryCategoryRepository = $inventoryCategoryRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->CSVExportService = $CSVExportService;
    }

    /**
     * @Route("/api-columns", name="ref_article_api_columns", options={"expose"=true}, methods="GET|POST")
     */
    public function apiColumns(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }

            $currentUser = $this->getUser(); /** @var Utilisateur $currentUser */
            $columnsVisible = $currentUser->getColumnVisible();
            $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
            $category = CategoryType::ARTICLE;
            $champs = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);

			$columns = [
				[
					"title" => 'Actions',
					"data" => 'Actions',
					'name' => 'Actions',
					"class" => (in_array('Actions', $columnsVisible) ? 'display' : 'hide'),

				],
				[
					"title" => 'Libellé',
					"data" => 'Libellé',
					'name' => 'Libellé',
					"class" => (in_array('Libellé', $columnsVisible) ? 'display' : 'hide'),

				],
				[
					"title" => 'Référence',
					"data" => 'Référence',
					'name' => 'Référence',
					"class" => (in_array('Référence', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Type',
					"data" => 'Type',
					'name' => 'Type',
					"class" => (in_array('Type', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Statut',
					"data" => 'Statut',
					'name' => 'Statut',
					"class" => (in_array('Statut', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Quantité disponible',
					"data" => 'Quantité disponible',
					'name' => 'Quantité disponible',
					"class" => (in_array('Quantité disponible', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Quantité en stock',
					"data" => 'Quantité stock',
					'name' => 'Quantité stock',
					"class" => (in_array('Quantité stock', $columnsVisible) ? 'display' : 'hide'),
				],
                [
                    "title" => 'Code barre',
                    "data" => 'Code barre',
                    'name' => 'Code barre',
                    "class" => (in_array('Code barre', $columnsVisible) ? 'display' : 'hide'),

                ],
				[
					"title" => 'Emplacement',
					"data" => 'Emplacement',
					'name' => 'Emplacement',
					"class" => (in_array('Emplacement', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Commentaire',
					"data" => 'Commentaire',
					'name' => 'Commentaire',
					"class" => (in_array('Commentaire', $columnsVisible) ? 'display' : 'hide'),
				],
                [
                    "title" => 'Commentaire d\'urgence',
                    "data" => 'Commentaire d\'urgence',
                    'name' => 'Commentaire d\'urgence',
                    "class" => (in_array('Commentaire d\'urgence', $columnsVisible) ? 'display' : 'hide'),
                ],
				[
					"title" => 'Seuil d\'alerte',
					"data" => 'Seuil d\'alerte',
					'name' => 'Seuil d\'alerte',
					"class" => (in_array('Seuil d\'alerte', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Seuil de sécurité',
					"data" => 'Seuil de sécurité',
					'name' => 'Seuil de sécurité',
					"class" => (in_array('Seuil de sécurité', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Prix unitaire',
					"data" => 'Prix unitaire',
					'name' => 'Prix unitaire',
					"class" => (in_array('Prix unitaire', $columnsVisible) ? 'display' : 'hide'),
				],
				[
					"title" => 'Dernier inventaire',
					"data" => 'Dernier inventaire',
					'name' => 'Dernier inventaire',
					"class" => (in_array('Dernier inventaire', $columnsVisible) ? 'display' : 'hide'),
				],
			];
			foreach ($champs as $champ) {
				$columns[] = [
					"title" => ucfirst(mb_strtolower($champ['label'])),
					"data" => $champ['label'],
					'name' => $champ['label'],
					"class" => (in_array($champ['label'], $columnsVisible) ? 'display' : 'hide'),
				];
			}

            return new JsonResponse($columns);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="ref_article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->refArticleDataService->getRefArticleDataByParams($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="reference_article_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $this->referenceArticleRepository->countByReference($data['reference']);

            if ($refAlreadyExist) {
                return new JsonResponse([
                	'success' => false,
					'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
					'codeError' => 'DOUBLON-REF'
				]);
            }
            $requiredCreate = true;

            $type = $typeRepository->find($data['type']);

            if ($data['emplacement'] !== null) {
                $emplacement = $this->emplacementRepository->find($data['emplacement']);
            } else {
                $emplacement = null; //TODO gérer message erreur (faire un return avec msg erreur adapté -> à ce jour un return false correspond forcément à une réf déjà utilisée)
            };
            $CLRequired = $this->champLibreRepository->getByTypeAndRequiredCreate($type);
            $msgMissingCL = '';
            foreach ($CLRequired as $CL) {
                if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                    $requiredCreate = false;
                    if (!empty($msgMissingCL)) $msgMissingCL .= ', ';
                    $msgMissingCL .= $CL['label'];
                }
            }

            if (!$requiredCreate) {
                return new JsonResponse(['success' => false, 'msg' => 'Veuillez renseigner les champs obligatoires : ' . $msgMissingCL]);
            }

            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, $data['statut']);

            switch($data['type_quantite']) {
                case 'article':
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_ARTICLE;
                    break;
                default:
                    $typeArticle = ReferenceArticle::TYPE_QUANTITE_REFERENCE;
                    break;
            }
            $refArticle = new ReferenceArticle();
            $refArticle
                ->setLibelle($data['libelle'])
                ->setReference($data['reference'])
                ->setCommentaire($data['commentaire'])
                ->setTypeQuantite($typeArticle)
                ->setPrixUnitaire(max(0, $data['prix']))
                ->setType($type)
                ->setIsUrgent($data['urgence'])
                ->setEmplacement($emplacement)
				->setBarCode($this->refArticleDataService->generateBarCode());

            if ($data['limitSecurity']) {
            	$refArticle->setLimitSecurity($data['limitSecurity']);
			}
            if ($data['limitWarning']) {
            	$refArticle->setLimitWarning($data['limitWarning']);
			}
            if ($data['emergency-comment-input']) {
                $refArticle->setEmergencyComment($data['emergency-comment-input']);
            }
            if ($data['categorie']) {
            	$category = $this->inventoryCategoryRepository->find($data['categorie']);
            	if ($category) $refArticle->setCategory($category);
			}
            if ($statut) $refArticle->setStatut($statut);
            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $refArticle->setQuantiteStock($data['quantite'] ? max($data['quantite'], 0) : 0); // protection contre quantités négatives
            } else {
                $refArticle->setQuantiteStock(0);
            }
            $refArticle->setQuantiteReservee(0);
            foreach ($data['frl'] as $frl) {
                $fournisseurId = explode(';', $frl)[0];
                $ref = explode(';', $frl)[1];
                $label = explode(';', $frl)[2];
                $fournisseur = $this->fournisseurRepository->find(intval($fournisseurId));

                // on vérifie que la référence article fournisseur n'existe pas déjà
                $refFournisseurAlreadyExist = $this->articleFournisseurRepository->findByReferenceArticleFournisseur($ref);
                if ($refFournisseurAlreadyExist) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Ce nom de référence article fournisseur existe déjà. Vous ne pouvez pas le recréer.'
                    ]);
                }

                $articleFournisseur = new ArticleFournisseur();
                $articleFournisseur
                    ->setReferenceArticle($refArticle)
                    ->setFournisseur($fournisseur)
                    ->setReference($ref)
                    ->setLabel($label);
                $entityManager->persist($articleFournisseur);

            }
            $entityManager->persist($refArticle);
            $entityManager->flush();
            $champsLibresKey = array_keys($data);

            foreach ($champsLibresKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = new ValeurChampLibre();
                    $valeurChampLibre
                        ->setValeur(is_array($data[$champs]) ? implode(";", $data[$champs]) : $data[$champs])
                        ->addArticleReference($refArticle)
                        ->setChampLibre($this->champLibreRepository->find($champs));
                    $entityManager->persist($valeurChampLibre);
                    $entityManager->flush();
                }
            }
            return new JsonResponse(['success' => true, 'new' => $this->refArticleDataService->dataRowRefArticle($refArticle)]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reference_article_index",  methods="GET|POST", options={"expose"=true})
     */
    public function index(EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
            return $this->redirectToRoute('access_denied');
        }

        $typeQuantite = [
            [
                'const' => 'QUANTITE_AR',
                'label' => 'référence',
            ],
            [
                'const' => 'QUANTITE_A',
                'label' => 'article',
            ]
        ];

        $categorieCL = $this->categorieCLRepository->findOneByLabel(CategorieCL::REFERENCE_ARTICLE);
        $category = CategoryType::ARTICLE;
        $champL = $this->champLibreRepository->getByCategoryTypeAndCategoryCL($category, $categorieCL);
        $champF[] = [
            'label' => 'Actions',
            'id' => 0,
            'typage' => ''
        ];
        $champF[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Code barre',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champF[] = [
            'label' => 'Type',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Statut',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Quantité stock',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Quantité disponible',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Emplacement',
            'id' => 0,
            'typage' => 'list'
        ];
        $champF[] = [
            'label' => 'Commentaire',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Commentaire d\'urgence',
            'id' => 0,
            'typage' => 'text'
        ];
        $champF[] = [
            'label' => 'Seuil de sécurité',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Seuil d\'alerte',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Prix unitaire',
            'id' => 0,
            'typage' => 'number'
        ];
        $champF[] = [
            'label' => 'Dernier inventaire',
            'id' => 0,
            'typage' => 'date'
        ];

        // champs pour recherche personnalisée (uniquement de type texte ou liste)
		$champsLText = $this->champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_TEXT);
		$champsLTList = $this->champLibreRepository->getByCategoryTypeAndCategoryCLAndType($category, $categorieCL, ChampLibre::TYPE_LIST);

		$champsFText[] = [
            'label' => 'Libellé',
            'id' => 0,
            'typage' => 'text'

        ];

        $champsFText[] = [
            'label' => 'Référence',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Code barre',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Fournisseur',
            'id' => 0,
            'typage' => 'text'

        ];
        $champsFText[] = [
            'label' => 'Référence Article Fournisseur',
            'id' => 0,
            'typage' => 'text'

        ];

        $champs = array_merge($champF, $champL);
        $champsSearch = array_merge($champsFText, $champsLText, $champsLTList);

        usort($champs, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
        });

		usort($champsSearch, function ($a, $b) {
			return strcasecmp($a['label'], $b['label']);
		});

        $typeRepository = $entityManager->getRepository(Type::class);
        $types = $typeRepository->findByCategoryLabel(CategoryType::ARTICLE);
        $inventoryCategories = $this->inventoryCategoryRepository->findAll();
        $emplacements = $this->emplacementRepository->findAll();
        $typeChampLibre =  [];
        $search = $this->getUser()->getRecherche();
        foreach ($types as $type) {
            $champsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' =>  $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }
        $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($this->getUser(), FiltreRef::CHAMP_FIXE_STATUT);

        return $this->render('reference_article/index.html.twig', [
            'champs' => $champs,
            'champsSearch' => $champsSearch,
            'recherches' => $search,
            'columnsVisibles' => $this->getUser()->getColumnVisible(),
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            'emplacements' => $emplacements,
            'typeQuantite' => $typeQuantite,
            'filters' => $this->filtreRefRepository->findByUserExceptChampFixe($this->getUser(), FiltreRef::CHAMP_FIXE_STATUT),
            'categories' => $inventoryCategories,
            'wantInactif' => !empty($filter) && $filter->getValue() === Article::STATUT_INACTIF
        ]);
    }

    /**
     * @Route("/api-modifier", name="reference_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle = $this->referenceArticleRepository->find((int)$data['id']);

            if ($refArticle) {
                $json = $this->refArticleDataService->getViewEditRefArticle($refArticle, $data['isADemand']);
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reference_article_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refId = intval($data['idRefArticle']);
            $refArticle = $this->referenceArticleRepository->find($refId);

            // on vérifie que la référence n'existe pas déjà
            $refAlreadyExist = $this->referenceArticleRepository->countByReference($data['reference'], $refId);

            if ($refAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Ce nom de référence existe déjà. Vous ne pouvez pas le recréer.',
                    'codeError' => 'DOUBLON-REF'
                ]);
            }
            if ($refArticle) {
                $response = $this->refArticleDataService->editRefArticle($refArticle, $data);
            } else {
                $response = ['success' => false, 'msg' => "Une erreur s'est produite lors de la modification de la référence."];
            }
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer", name="reference_article_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $refArticle = $this->referenceArticleRepository->find($data['refArticle']);
            $rows = $refArticle->getId();
            $entityManager = $this->getDoctrine()->getManager();
            if (count($refArticle->getCollecteReferences()) > 0
                || count($refArticle->getLigneArticles()) > 0
                || count($refArticle->getReceptionReferenceArticles()) > 0
                || count($refArticle->getArticlesFournisseur()) > 0) {
                return new JsonResponse(false, 250);
            }
            $entityManager->remove($refArticle);
            $entityManager->flush();

            $response['delete'] = $rows;
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/addFournisseur", name="ajax_render_add_fournisseur", options={"expose"=true}, methods="GET|POST")
     */
    public function addFournisseur(Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $json =  $this->renderView('reference_article/fournisseurArticle.html.twig');
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/removeFournisseur", name="ajax_render_remove_fournisseur", options={"expose"=true}, methods="GET|POST")
     */
    public function removeFournisseur(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $em = $this->getDoctrine()->getManager();
            $em->remove($this->articleFournisseurRepository->find($data['articleF']));
            $em->flush();
            $json =  $this->renderView('reference_article/fournisseurArticleContent.html.twig', [
                'articles' => $this->articleFournisseurRepository->findByRefArticle($data['articleRef']),
                'articleRef' => $this->referenceArticleRepository->find($data['articleRef'])
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/quantite", name="get_quantity_ref_article", options={"expose"=true})
     */
    public function getQuantityByRefArticleId(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::DEM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $quantity = false;

            $refArticleId = $request->request->get('refArticleId');
            $refArticle = $this->referenceArticleRepository->find($refArticleId);

            if ($refArticle) {
				if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
					$quantity = $refArticle->getQuantiteStock();
				}
			}

            return new JsonResponse($quantity);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/autocomplete-ref/{activeOnly}/type/{typeQuantity}", name="get_ref_articles", options={"expose"=true}, methods="GET|POST")
	 *
	 * @param Request $request
	 * @param bool $activeOnly
	 * @return JsonResponse
	 */
    public function getRefArticles(Request $request, $activeOnly = false, $typeQuantity = null)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $refArticles = $this->referenceArticleRepository->getIdAndRefBySearch($search, $activeOnly, $typeQuantity);

            return new JsonResponse(['results' => $refArticles]);
        }
        throw new NotFoundHttpException("404");
    }

	/**
	 * @Route("/autocomplete-ref-and-article/{activeOnly}", name="get_ref_and_articles", options={"expose"=true}, methods="GET|POST")
	 *
	 * @param Request $request
	 * @param bool $activeOnly
	 * @return JsonResponse
	 */
	public function getRefAndArticles(Request $request, $activeOnly = false)
	{
		if ($request->isXmlHttpRequest()) {
			$search = $request->query->get('term');

			$refArticles = $this->referenceArticleRepository->getIdAndRefBySearch($search, $activeOnly);
			$articles = $this->articleRepository->getIdAndRefBySearch($search, $activeOnly);

			return new JsonResponse(['results' => array_merge($articles, $refArticles)]);
		}
		throw new NotFoundHttpException("404");
	}

    /**
     * @Route("/plus-demande", name="plus_demande", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function plusDemande(EntityManagerInterface $entityManager,
                                Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $json = true;

            $refArticle = (isset($data['refArticle']) ? $this->referenceArticleRepository->find($data['refArticle']) : '');

            $statutRepository = $entityManager->getRepository(Statut::class);

            $statusName = $refArticle->getStatut() ? $refArticle->getStatut()->getNom() : '';
            if ($statusName == ReferenceArticle::STATUT_ACTIF) {

				if (array_key_exists('livraison', $data) && $data['livraison']) {
					$json = $this->refArticleDataService->addRefToDemand($data, $refArticle);
					if ($json === 'article') {
						$this->articleDataService->editArticle($data);
						$json = true;
					}

				} elseif (array_key_exists('collecte', $data) && $data['collecte']) {
					$collecte = $this->collecteRepository->find($data['collecte']);
					if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
						//TODO patch temporaire CEA
						$fournisseurTemp = $this->fournisseurRepository->findOneByCodeReference('A_DETERMINER');
						if (!$fournisseurTemp) {
							$fournisseurTemp = new Fournisseur();
							$fournisseurTemp
								->setCodeReference('A_DETERMINER')
								->setNom('A DETERMINER');
							$em->persist($fournisseurTemp);
						}
						$newArticle = new Article();
						$index = $this->articleFournisseurRepository->countByRefArticle($refArticle);
						$statut = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_INACTIF);
						$date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
						$ref = $date->format('YmdHis');
						$articleFournisseur = new ArticleFournisseur();
						$articleFournisseur
							->setReferenceArticle($refArticle)
							->setFournisseur($fournisseurTemp)
							->setReference($refArticle->getReference())
							->setLabel('A déterminer -' . $index);
						$em->persist($articleFournisseur);
						$newArticle
							->setLabel($refArticle->getLibelle() . '-' . $index)
							->setConform(true)
							->setStatut($statut)
							->setReference($ref . '-' . $index)
							->setQuantite(max($data['quantite'], 0)) // protection contre quantités négatives
							//TODO quantite, quantitie ?
							->setEmplacement($collecte->getPointCollecte())
							->setArticleFournisseur($articleFournisseur)
							->setType($refArticle->getType())
							->setBarCode($this->articleDataService->generateBarCode());
						$em->persist($newArticle);
						$collecte->addArticle($newArticle);
						//TODO fin patch temporaire CEA (à remplacer par lignes suivantes)
						//                    $article = $this->articleRepository->find($data['article']);
						//                    $collecte->addArticle($article);
					} elseif ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
						$collecteReference = new CollecteReference;
						$collecteReference
							->setCollecte($collecte)
							->setReferenceArticle($refArticle)
							->setQuantite(max((int)$data['quantitie'], 0)); // protection contre quantités négatives
						$em->persist($collecteReference);
					} else {
						$json = false; //TOOD gérer message erreur
					}
				} else {
					$json = false; //TOOD gérer message erreur
				}
				$em->flush();
			} else {
            	$json = false;
			}

            return new JsonResponse($json);

        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajax-plus-demande-content", name="ajax_plus_demande_content", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws DBALException
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function ajaxPlusDemandeContent(EntityManagerInterface $entityManager,
                                           Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);

            $refArticle = $this->referenceArticleRepository->find($data['id']);
            if ($refArticle) {
                $collectes = $this->collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

                $statutD = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
                $demandes = $this->demandeRepository->findByStatutAndUser($statutD, $this->getUser());

                if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    if ($refArticle) {
                        $editChampLibre  = $this->refArticleDataService->getViewEditRefArticle($refArticle, true);
                    } else {
                        $editChampLibre = false;
                    }
                } else {
                    //TODO patch temporaire CEA
					$isCea = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_CEA_LETI);
                    if ($isCea && $refArticle->getStatut()->getNom() === ReferenceArticle::STATUT_INACTIF && $data['demande'] === 'collecte') {
                        $response = [
                            'plusContent' => $this->renderView('reference_article/modalPlusDemandeTemp.html.twig', [
                                'collectes' => $collectes
                            ]),
                            'temp' => true
                        ];
                        return new JsonResponse($response);
                    }
                    //TODO fin de patch temporaire CEA
                    $editChampLibre = false;
                }

				$byRef = $this->userService->hasParamQuantityByRef();
                $articleOrNo  = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, $data['demande'], false, $byRef);

                $json = [
                    'plusContent' => $this->renderView(
                        'reference_article/modalPlusDemandeContent.html.twig',
                        [
                            'articleOrNo' => $articleOrNo,
                            'collectes' => $collectes,
                            'demandes' => $demandes
                        ]
                    ),
                    'editChampLibre' => $editChampLibre,
					'byRef' => $byRef && $refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE
				];
            } else {
                $json = false;
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible", options={"expose"=true}, methods="GET|POST")
     */
    public function saveColumnVisible(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $champs = array_keys($data);
            $user  = $this->getUser();
            /** @var $user Utilisateur */
            $user->setColumnVisible($champs);
            $em  = $this->getDoctrine()->getManager();
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/est-urgent", name="is_urgent", options={"expose"=true}, methods="GET|POST")
     */
    public function isUrgent(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $id = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $referenceArticle = $this->referenceArticleRepository->find($id);
            return new JsonResponse([
                'urgent' => $referenceArticle->getIsUrgent() ?? false,
                'comment' => $referenceArticle->getEmergencyComment()
            ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir", name="reference_article_show", options={"expose"=true})
     */
    public function show(Request $request,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::DISPLAY_REFE)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle  = $this->referenceArticleRepository->find($data);

            $data = $this->refArticleDataService->getDataEditForRefArticle($refArticle);
            $articlesFournisseur = $this->articleFournisseurRepository->findByRefArticle($refArticle->getId());

            $typeRepository = $entityManager->getRepository(Type::class);
            $types = $typeRepository->findByCategoryLabel(CategoryType::ARTICLE);

            $typeChampLibre =  [];
            foreach ($types as $type) {
                $champsLibresComplet = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);

                $champsLibres = [];
                foreach ($champsLibresComplet as $champLibre) {
                    $valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refArticle->getId(), $champLibre);
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampRefArticle,
                    ];
                }
                $typeChampLibre[] = [
                    'typeLabel' =>  $type->getLabel(),
					'typeId' => $type->getId(),
                    'champsLibres' => $champsLibres,
                ];
            }
            //reponse Vue + data

            if ($refArticle) {
                $view =  $this->templating->render('reference_article/modalRefArticleContent.html.twig', [
                    'articleRef' => $refArticle,
                    'statut' => $refArticle->getStatut() ? $refArticle->getStatut()->getNom() : null,
                    'valeurChampLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                    'typeChampsLibres' => $typeChampLibre,
                    'articlesFournisseur' => ($data['listArticlesFournisseur']),
                    'totalQuantity' => $data['totalQuantity'],
                    'articles' => $articlesFournisseur,
                ]);

                $json = $view;
            } else {
                return $json = false;
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/exporter/{min}/{max}", name="reference_article_export", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param $max
     * @param $min
     * @return Response
     */
    public function exportAll(EntityManagerInterface $entityManager,
                              Request $request,
                              $max,
                              $min): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data = [];
            $data['values'] = [];
            $headersCL = [];
            foreach ($this->champLibreRepository->findAll() as $champLibre) {
                $headersCL[] = $champLibre->getLabel();
            }
            $typeRepository = $entityManager->getRepository(Type::class);
            $listTypes = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::ARTICLE);

            $references = $this->referenceArticleRepository->getBetweenLimits($min, $max-$min);
            foreach ($references as $reference) {
                $data['values'][] = $this->buildInfos($typeRepository, $reference, $listTypes, $headersCL);
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/export-donnees", name="exports_params")
     */
    public function renderParams()
    {
        return $this->render('exports/exportsMenu.html.twig');
    }

    /**
     * @Route("/total", name="get_total_and_headers_ref", options={"expose"=true}, methods="GET|POST")
     */
    public function total(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            $data['total'] = $this->referenceArticleRepository->countAll();
            $data['headers'] = [
                'reference',
                'libellé',
                'quantité',
                'type',
                'type quantité',
                'statut',
                'commentaire',
                'emplacement',
                'fournisseurs',
                'articles fournisseurs',
                'seuil sécurite',
                'seuil alerte',
                'prix unitaire',
                'code barre',
				'catégorie inventaire',
				'date dernier inventaire'
            ];
            foreach ($this->champLibreRepository->findAll() as $champLibre) {
                $data['headers'][] = $champLibre->getLabel();
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @param TypeRepository $typeRepository
     * @param ReferenceArticle $ref
     * @param array $listTypes
     * @param string[] $headersCL
     * @return string
     */
    public function buildInfos(TypeRepository $typeRepository, ReferenceArticle $ref, $listTypes, $headersCL)
    {
    	$listFournisseurAndAF = $this->fournisseurRepository->getNameAndRefArticleFournisseur($ref);

    	$arrayAF = $arrayF = [];

    	foreach ($listFournisseurAndAF as $fournisseurAndAF) {
    		$arrayAF[] = $fournisseurAndAF['reference'];
    		$arrayF[] = $fournisseurAndAF['nom'];
		}

    	$stringArticlesFournisseur = implode(' / ', $arrayAF);
    	$stringFournisseurs = implode(' / ', $arrayF);

        $refData[] = $this->CSVExportService->escapeCSV($ref->getReference());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLibelle());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getQuantiteStock());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getType() ? $ref->getType()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($ref->getTypeQuantite());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getStatut() ? $ref->getStatut()->getNom() : '');
        $refData[] = $this->CSVExportService->escapeCSV(strip_tags($ref->getCommentaire()));
        $refData[] = $this->CSVExportService->escapeCSV($ref->getEmplacement() ? $ref->getEmplacement()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($stringFournisseurs);
        $refData[] = $this->CSVExportService->escapeCSV($stringArticlesFournisseur);
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLimitSecurity());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getLimitWarning());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getPrixUnitaire());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getBarCode());
        $refData[] = $this->CSVExportService->escapeCSV($ref->getCategory() ? $ref->getCategory()->getLabel() : '');
        $refData[] = $this->CSVExportService->escapeCSV($ref->getDateLastInventory() ? $ref->getDateLastInventory()->format('d/m/Y') : '');

        $champsLibres = [];
        foreach ($listTypes as $typeArray) {
        	$type = $typeRepository->find($typeArray['id']);
            $listChampsLibres = $this->champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            foreach ($listChampsLibres as $champLibre) {
                $valeurChampRefArticle = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($ref->getId(), $champLibre);
                if ($valeurChampRefArticle) $champsLibres[$champLibre->getLabel()] = $valeurChampRefArticle->getValeur();
            }
        }
        foreach ($headersCL as $type) {
            if (array_key_exists($type, $champsLibres)) {
                $refData[] = $champsLibres[$type];
            } else {
                $refData[] = '';
            }
        }
        return implode(';', $refData);
    }

	/**
	 * @Route("/type-quantite", name="get_quantity_type", options={"expose"=true}, methods="GET|POST")
	 */
    public function getQuantityType(Request $request)
	{
		if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			$reference = $this->referenceArticleRepository->find($data['id']);

			$quantityType = $reference ? $reference->getTypeQuantite() : '';

			return new JsonResponse($quantityType);
		}
		throw new NotFoundHttpException('404');
	}

    /**
     * @Route("/get-demande", name="demande", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getDemande(EntityManagerInterface $entityManager,
                               Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data= json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);

            $statutDemande = $statutRepository->findOneByCategorieNameAndStatutCode(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
            $demandes = $this->demandeRepository->findByStatutAndUser($statutDemande, $this->getUser());

            $collectes = $this->collecteRepository->findByStatutLabelAndUser(Collecte::STATUT_BROUILLON, $this->getUser());

            if ($data['typeDemande'] === 'livraison' && $demandes) {
                $json = $demandes;
            } elseif ($data['typeDemande'] === 'collecte' && $collectes) {
                $json = $collectes;
            } else {
                $json = false;
            }
            return new JsonResponse($json);
        }

        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/etiquettes", name="reference_article_bar_codes_print", options={"expose"=true})
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getBarCodes(Request $request,
                                RefArticleDataService $refArticleDataService,
                                PDFGeneratorService $PDFGeneratorService): Response {
        $userId = $this->user->getId();
        $filters = $this->filtreRefRepository->getFieldsAndValuesByUser($userId);
        $queryResult = $this->referenceArticleRepository->findByFiltersAndParams($filters, $request->query, $this->user);
        $refs = $queryResult['data'];
        $refs = array_map(function($refArticle) {
            return is_array($refArticle) ? $refArticle[0] : $refArticle;
        }, $refs);
        $barcodeConfigs = array_map(
            function (ReferenceArticle $reference) use ($refArticleDataService) {
                return $refArticleDataService->getBarcodeConfig($reference);
            },
            $refs
        );

        $barcodeCounter = count($barcodeConfigs);

        if ($barcodeCounter > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barcodeConfigs,
                'reference' . ($barcodeCounter > 1 ? 's' : '')
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
                $fileName
            );
        }
        else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/{reference}/etiquette", name="reference_article_single_bar_code_print", options={"expose"=true})
     * @param ReferenceArticle $reference
     * @param RefArticleDataService $refArticleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSingleBarCodes(ReferenceArticle $reference,
                                      RefArticleDataService $refArticleDataService,
                                      PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($reference)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'reference');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route("/show-actif-inactif", name="reference_article_actif_inactif", options={"expose"=true})
     */
    public function displayActifOrInactif(Request $request) : Response
    {
        if ($request->isXmlHttpRequest() && $data= json_decode($request->getContent(), true)){

            $user = $this->getUser();
            $statutArticle = $data['donnees'];

            $filter = $this->filtreRefRepository->findOneByUserAndChampFixe($user, FiltreRef::CHAMP_FIXE_STATUT);

            $em = $this->getDoctrine()->getManager();
            if($filter == null){
                $filter = new FiltreRef();
                $filter
                    ->setUtilisateur($user)
                    ->setChampFixe('Statut')
                    ->setValue(ReferenceArticle::STATUT_ACTIF);
                $em->persist($filter);
            }

            if ($filter->getValue() != $statutArticle) {
                $filter->setValue($statutArticle);
            }
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/mouvements/lister", name="ref_mouvements_list", options={"expose"=true}, methods="GET|POST")
     */
    public function showMovements(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            if ($ref = $this->referenceArticleRepository->find($data)) {
                $name = $ref->getLibelle();
            }

           return new JsonResponse($this->renderView('reference_article/modalShowMouvementsContent.html.twig', [
               'refLabel' => $name?? ''
           ]));
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/mouvements/api/{id}", name="ref_mouvements_api", options={"expose"=true}, methods="GET|POST")
     */
    public function apiMouvements(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) {

            $mouvements = $this->mouvementStockRepository->findByRef($id);

            $rows = [];
            foreach ($mouvements as $mouvement) {
                $rows[] =
                    [
                        'Date' => $mouvement->getDate() ? $mouvement->getDate()->format('d/m/Y H:i:s') : 'aucune',
                        'Quantity' => $mouvement->getQuantity(),
                        'Origin' => $mouvement->getEmplacementFrom() ? $mouvement->getEmplacementFrom()->getLabel() : 'aucun',
                        'Destination' => $mouvement->getEmplacementTo() ? $mouvement->getEmplacementTo()->getLabel() : 'aucun',
                        'Type' => $mouvement->getType(),
                        'Operator' => $mouvement->getUser() ? $mouvement->getUser()->getUsername() : 'aucun'
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
