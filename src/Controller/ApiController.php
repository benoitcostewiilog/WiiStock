<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 05/03/2019
 * Time: 14:31
 */

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\InventoryEntry;
use App\Entity\Livraison;
use App\Entity\Manutention;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\MouvementTraca;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Repository\ColisRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\InventoryMissionRepository;
use App\Repository\LigneArticleRepository;
use App\Repository\LivraisonRepository;
use App\Repository\MailerServerRepository;
use App\Repository\ManutentionRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\MouvementTracaRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\PieceJointeRepository;
use App\Repository\PreparationRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Service\ArticleDataService;
use App\Service\InventoryService;
use App\Service\MailerService;
use App\Service\Nomade\PreparationsManagerService;
use App\Service\OrdreCollecteService;
use App\Service\UserService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use DateTime;
use Throwable;


/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends AbstractFOSRestController implements ClassResourceInterface
{

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

    /**
     * @var array
     */
    private $successDataMsg;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

    /**
     * @var PreparationRepository
     */
    private $preparationRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var LivraisonRepository
     */
    private $livraisonRepository;

    /**
     * @var MouvementStockRepository
     */
    private $mouvementRepository;

    /**
     * @var LigneArticleRepository
     */
    private $ligneArticleRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InventoryService
     */
    private $inventoryService;

    /**
     * @var ManutentionRepository
     */
    private $manutentionRepository;

    /**
     * @var OrdreCollecteRepository
     */
    private $ordreCollecteRepository;

    /**
     * @var OrdreCollecteService
     */
    private $ordreCollecteService;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

    /**
     * ApiController constructor.
     * @param InventoryEntryRepository $inventoryEntryRepository
     * @param ManutentionRepository $manutentionRepository
     * @param OrdreCollecteService $ordreCollecteService
     * @param OrdreCollecteRepository $ordreCollecteRepository
     * @param InventoryService $inventoryService
     * @param UserService $userService
     * @param InventoryMissionRepository $inventoryMissionRepository
     * @param FournisseurRepository $fournisseurRepository
     * @param LigneArticleRepository $ligneArticleRepository
     * @param MouvementStockRepository $mouvementRepository
     * @param LivraisonRepository $livraisonRepository
     * @param ArticleDataService $articleDataService
     * @param StatutRepository $statutRepository
     * @param PreparationRepository $preparationRepository
     * @param PieceJointeRepository $pieceJointeRepository
     * @param LoggerInterface $logger
     * @param MailerServerRepository $mailerServerRepository
     * @param MailerService $mailerService
     * @param ColisRepository $colisRepository
     * @param MouvementTracaRepository $mouvementTracaRepository
     * @param ReferenceArticleRepository $referenceArticleRepository
     * @param UtilisateurRepository $utilisateurRepository
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param ArticleRepository $articleRepository
     * @param EmplacementRepository $emplacementRepository
     */
    public function __construct(InventoryEntryRepository $inventoryEntryRepository, ManutentionRepository $manutentionRepository, OrdreCollecteService $ordreCollecteService, OrdreCollecteRepository $ordreCollecteRepository, InventoryService $inventoryService, UserService $userService, InventoryMissionRepository $inventoryMissionRepository, FournisseurRepository $fournisseurRepository, LigneArticleRepository $ligneArticleRepository, MouvementStockRepository $mouvementRepository, LivraisonRepository $livraisonRepository, ArticleDataService $articleDataService, StatutRepository $statutRepository, PreparationRepository $preparationRepository, PieceJointeRepository $pieceJointeRepository, LoggerInterface $logger, MailerServerRepository $mailerServerRepository, MailerService $mailerService, ColisRepository $colisRepository, MouvementTracaRepository $mouvementTracaRepository, ReferenceArticleRepository $referenceArticleRepository, UtilisateurRepository $utilisateurRepository, UserPasswordEncoderInterface $passwordEncoder, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository)
    {
        $this->manutentionRepository = $manutentionRepository;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->mailerServerRepository = $mailerServerRepository;
        $this->mailerService = $mailerService;
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordEncoder = $passwordEncoder;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->successDataMsg = ['success' => false, 'data' => [], 'msg' => ''];
        $this->logger = $logger;
        $this->preparationRepository = $preparationRepository;
        $this->statutRepository = $statutRepository;
        $this->articleDataService = $articleDataService;
        $this->livraisonRepository = $livraisonRepository;
        $this->mouvementRepository = $mouvementRepository;
        $this->ligneArticleRepository = $ligneArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->userService = $userService;
        $this->inventoryService = $inventoryService;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->ordreCollecteService = $ordreCollecteService;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
    }

    /**
     * @Rest\Post("/api/connect", name= "api-connect")
     * @Rest\Get("/api/connect")
     * @Rest\View()
     */
    public function connection(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            $user = $this->utilisateurRepository->findOneBy(['username' => $data['login']]);

            if ($user !== null) {
                if ($this->passwordEncoder->isPasswordValid($user, $data['password'])) {
                    $apiKey = $this->apiKeyGenerator();

                    $user->setApiKey($apiKey);
                    $em = $this->getDoctrine()->getManager();
                    $em->flush();

                    $isInventoryManager = $this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER, $user);

                    $this->successDataMsg['success'] = true;
                    $this->successDataMsg['data'] = [
                        'isInventoryManager' => $isInventoryManager,
                        'apiKey' => $apiKey
                    ];
                }
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/ping", name= "api-ping")
     * @Rest\Get("/api/ping")
     * @Rest\View()
     */
    public function ping(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            $response = new Response();

            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
            $this->successDataMsg['success'] = true;

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/addMouvementTraca", name="api-add-mouvement-traca")
     * @Rest\Get("/api/addMouvementTraca")
     * @Rest\View()
     */
    public function addMouvementTraca(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();
                $numberOfRowsInserted = 0;
                foreach ($data['mouvements'] as $mvt) {
                    if (!$this->mouvementTracaRepository->getOneByDate($mvt['date'])) {
                        $refEmplacement = $mvt['ref_emplacement'];
                        $refArticle = $mvt['ref_article'];
                        $type = $mvt['type'];

                        $toInsert = new MouvementTraca();
                        $toInsert
                            ->setRefArticle($refArticle)
                            ->setRefEmplacement($refEmplacement)
                            ->setOperateur($this->utilisateurRepository->findOneByApiKey($data['apiKey'])->getUsername())
                            ->setDate($mvt['date'])
                            ->setType($type);
                        $em->persist($toInsert);
                        $numberOfRowsInserted++;

                        $emplacement = $this->emplacementRepository->findOneByLabel($refEmplacement);

                        if ($emplacement) {

                            $isDepose = $type === MouvementTraca::TYPE_DEPOSE;
                            $colis = $this->colisRepository->findOneByCode($mvt['ref_article']);

                            if ($isDepose && $colis && $emplacement->getIsDeliveryPoint()) {
                                $fournisseur = $this->fournisseurRepository->findOneByColis($colis);
                                $arrivage = $colis->getArrivage();
                                $destinataire = $arrivage->getDestinataire();
                                if ($this->mailerServerRepository->findOneMailerServer()) {
                                    $dateArray = explode('_', $toInsert->getDate());
                                    $date = new DateTime($dateArray[0]);
                                    $this->mailerService->sendMail(
                                        'FOLLOW GT // Dépose effectuée',
                                        $this->renderView(
                                            'mails/mailDeposeTraca.html.twig',
                                            [
                                                'title' => 'Votre colis a été livré.',
                                                'colis' => $colis->getCode(),
                                                'emplacement' => $emplacement,
                                                'fournisseur' => $fournisseur ? $fournisseur->getNom() : '',
                                                'date' => $date,
                                                'operateur' => $toInsert->getOperateur(),
                                                'pjs' => $arrivage->getAttachements()
                                            ]
                                        ),
                                        $destinataire->getEmail()
                                    );
                                } else {
                                    $this->logger->critical('Parametrage mail non defini.');
                                }
                            }
                        } else {
                            $emplacement = new Emplacement();
                            $emplacement->setLabel($refEmplacement);
                            $em->persist($emplacement);
                            $em->flush();
                        }
                    }
                }
                $em->flush();

                $s = $numberOfRowsInserted > 0 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
                    'Aucun mouvement à synchroniser.' : $numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s;

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

    /**
     * @Rest\Post("/api/setmouvement", name= "api-set-mouvement")
     * @Rest\View()
     */
    public function setMouvement(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $mouvementsR = $data['mouvement'];
                foreach ($mouvementsR as $mouvementR) {
                    $mouvement = new MouvementStock;
                    $mouvement
                        ->setType($mouvementR['type'])
                        ->setDate(DateTime::createFromFormat('j-M-Y', $mouvementR['date']))
                        ->setEmplacementFrom($this->emplacemnt->$mouvementR[''])
                        ->setUser($mouvementR['']);
                }
                $this->successDataMsg['success'] = true;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/beginPrepa", name= "api-begin-prepa")
     * @Rest\View()
     * @param Request $request
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function beginPrepa(Request $request,
                               PreparationsManagerService $preparationsManager)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $preparation = $this->preparationRepository->find($data['id']);
                $preparationDone = $preparationsManager->beginPrepa($preparation, $nomadUser);

                if ($preparationDone) {
                    $this->successDataMsg['success'] = true;
                }
                else {
                    $this->successDataMsg['success'] = false;
                    $this->successDataMsg['msg'] = "Cette préparation a déjà été prise en charge par un opérateur.";
                    $this->successDataMsg['data'] = [];
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/finishPrepa", name= "api-finish-prepa")
     * @Rest\View()
     * @param Request $request
     * @param PreparationsManagerService $preparationsManager
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     * @throws Throwable
     */
    public function finishPrepa(Request $request,
                                PreparationsManagerService $preparationsManager,
                                EntityManagerInterface $entityManager) {
        $resData = [];
        $statusCode = Response::HTTP_OK;
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $resData = ['success' => [], 'errors' => []];

                $preparations = $data['preparations'];
                $date = new DateTime();

                // on termine les préparations
                // même comportement que LivraisonController.new()
                foreach ($preparations as $preparationArray) {
                    $preparation = $this->preparationRepository->find($preparationArray['id']);

                    if ($preparation) {
                        // if it has not been begin
                        $preparationsManager->beginPrepa($preparation, $nomadUser);
                        try {
                            // we create a new entity manager because transactional() can call close() on it if the transaction fail
                            // So there is one $localEntityManager for each transaction and we don't use the global entityManager to don"t close t
                            $localEntityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                            // flush auto at the end
                            $localEntityManager->transactional(function()
                                                               use ($preparationsManager, $preparationArray, $preparation, $nomadUser, $date) {
                                $livraison = $preparationsManager->persistLivraison($preparationArray);
                                $preparationsManager->treatPreparation($preparation, $livraison, $nomadUser);
                                $preparationsManager->closePreparationMouvement($preparation, $preparationArray['emplacement'], $date);

                                $mouvementsNomade = $preparationArray['mouvements'];
                                // on crée les mouvements de livraison
                                foreach ($mouvementsNomade as $mouvementNomade) {
                                    $preparationsManager->treatMouvement($mouvementNomade, $nomadUser, $livraison);
                                }
                            });

                            $resData['success'][] = [
                                'numero_prepa' => $preparation->getNumero(),
                                'id_prepa' => $preparation->getId()
                            ];
                        }
                        catch (\Exception $exception) {
                            $resData['errors'][] = [
                                'numero_prepa' => $preparation->getNumero(),
                                'id_prepa' => $preparation->getId(),

                                'message' => (
                                    ($exception->getMessage() === PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                                    (($exception->getMessage() === PreparationsManagerService::ARTICLE_ALREADY_SELECTED) ? "L'article n'est pas sélectionnable" :
                                        'Une erreur est survenue')
                                )
                            ];
                        }
                    }
                }

                $preparationsManager->removeRefMouvements();
                $entityManager->flush();

            } else {
                $statusCode = Response::HTTP_UNAUTHORIZED;
                $resData['success'] = false;
                $resData['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($resData, $statusCode);
        }
    }

    /**
     * @Rest\Post("/api/beginLivraison", name= "api-begin-livraison")
     * @Rest\View()
     */
    public function beginLivraison(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $livraison = $this->livraisonRepository->find($data['id']);

                if (
                    $livraison->getStatut()->getNom() == Livraison::STATUT_A_TRAITER &&
                    (empty($livraison->getUtilisateur()) || $livraison->getUtilisateur() === $nomadUser)
                ) {
                    // modif de la livraison
                    $livraison->setUtilisateur($nomadUser);

                    $em->flush();

                    $this->successDataMsg['success'] = true;
                } else {
                    $this->successDataMsg['success'] = false;
                    $this->successDataMsg['msg'] = "Cette livraison a déjà été prise en charge par un opérateur.";
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/beginCollecte", name= "api-begin-collecte")
     * @Rest\View()
     */
    public function beginCollecte(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $ordreCollecte = $this->ordreCollecteRepository->find($data['id']);

                if (
                    $ordreCollecte->getStatut()->getNom() == OrdreCollecte::STATUT_A_TRAITER &&
                    (empty($ordreCollecte->getUtilisateur()) || $ordreCollecte->getUtilisateur() === $nomadUser)
                ) {
                    // modif de la collecte
                    $ordreCollecte->setUtilisateur($nomadUser);

                    $em->flush();

                    $this->successDataMsg['success'] = true;
                } else {
                    $this->successDataMsg['success'] = false;
                    $this->successDataMsg['msg'] = "Cette collecte a déjà été prise en charge par un opérateur.";
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/validateManut", name= "api-validate-manut")
     * @Rest\View()
     */
    public function validateManut(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();

                $manut = $this->manutentionRepository->find($data['id']);

                if ($manut->getStatut()->getNom() == Livraison::STATUT_A_TRAITER) {
                    if ($data['commentaire'] !== "") {
                        $manut->setCommentaire($manut->getCommentaire() . "\n" . date('d/m/y H:i:s') . " - " . $nomadUser->getUsername() . " :\n" . $data['commentaire']);
                    }
                    $manut->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MANUTENTION, Manutention::STATUT_TRAITE));
                    $em->flush();
                    if ($manut->getStatut()->getNom() == Manutention::STATUT_TRAITE) {
                        $this->mailerService->sendMail(
                            'FOLLOW GT // Manutention effectuée',
                            $this->renderView('mails/mailManutentionDone.html.twig', [
                                'manut' => $manut,
                                'title' => 'Votre demande de manutention a bien été effectuée.',
                            ]),
                            $manut->getDemandeur()->getEmail()
                        );
                    }
                    $this->successDataMsg['success'] = true;
                } else {
                    $this->successDataMsg['success'] = false;
                    $this->successDataMsg['msg'] = "Cette manutention a déjà été prise en charge par un opérateur.";
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }
            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/finishLivraison", name= "api-finish-livraison")
     * @Rest\View()
     */
    public function finishLivraison(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $entityManager = $this->getDoctrine()->getManager();

                $livraisons = $data['livraisons'];

                // on termine les livraisons
                // même comportement que LivraisonController.finish()
                foreach ($livraisons as $livraisonArray) {
                    $livraison = $this->livraisonRepository->find($livraisonArray['id']);

                    if ($livraison) {
                        $date = DateTime::createFromFormat(DateTime::ATOM, $livraisonArray['date_end']);

                        $livraison
                            ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_LIVRE))
                            ->setUtilisateur($nomadUser)
                            ->setDateFin($date);

                        $demandes = $livraison->getDemande();
                        $demande = $demandes[0];

                        $statutLivre = $this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_LIVRE);
                        $demande->setStatut($statutLivre);

                        $this->mailerService->sendMail(
                            'FOLLOW GT // Livraison effectuée',
                            $this->renderView('mails/mailLivraisonDone.html.twig', [
                                'livraison' => $demande,
                                'title' => 'Votre demande a bien été livrée.',
                            ]),
                            $demande->getUtilisateur()->getEmail()
                        );

                        // quantités gérées à la référence
                        $ligneArticles = $demande->getLigneArticle();

                        foreach ($ligneArticles as $ligneArticle) {
                            $refArticle = $ligneArticle->getReference();
                            $refArticle->setQuantiteStock($refArticle->getQuantiteStock() - $ligneArticle->getQuantite());
                        }

                        // quantités gérées à l'article
                        $articles = $demande->getArticles();

                        foreach ($articles as $article) {
                            $article
                                ->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_INACTIF))
                                ->setEmplacement($demande->getDestination());
                        }

                        // on termine les mouvements de livraison
                        $mouvements = $this->mouvementRepository->findByLivraison($livraison);
                        foreach ($mouvements as $mouvement) {
                            $emplacement = $this->emplacementRepository->findOneByLabel($livraisonArray['emplacement']);
                            if ($emplacement) {
                                $mouvement
                                    ->setDate($date)
                                    ->setEmplacementTo($emplacement);
                            } else {
                                $this->successDataMsg['success'] = false;
                                $this->successDataMsg['msg'] = "L'emplacement que vous avez sélectionné n'existe plus.";
                                return new JsonResponse($this->successDataMsg);
                            }
                        }

                        $entityManager->flush();
                    }
                }

                $this->successDataMsg['success'] = true;

            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/finishCollecte", name= "api-finish-collecte")
     * @Rest\View()
     */
    public function finishCollecte(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $collectes = $data['collectes'];

                // on termine les collectes
                foreach ($collectes as $collecteArray) {
                    $collecte = $this->ordreCollecteRepository->find($collecteArray['id']);

                    if ($collecte->getStatut() && $collecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER) {
                        $date = DateTime::createFromFormat(DateTime::ATOM, $collecteArray['date_end']);
                        $this->ordreCollecteService->finishCollecte($collecte, $nomadUser, $date);
                        $this->successDataMsg['success'] = true;
                    } else {
                        $user = $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '';
                        $this->successDataMsg['success'] = false;
                        $this->successDataMsg['msg'] = "La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ").";
                    }
                }
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg);
        }
    }

    /**
     * @Rest\Post("/api/addInventoryEntries", name= "api-add-inventory-entry")
     * @Rest\Get("/api/addInventoryEntries")
     * @Rest\View()
     */
    public function addInventoryEntries(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $em = $this->getDoctrine()->getManager();
                $numberOfRowsInserted = 0;

                foreach ($data['entries'] as $entry) {
                    $newEntry = new InventoryEntry();

                    $mission = $this->inventoryMissionRepository->find($entry['id_mission']);
                    $location = $this->emplacementRepository->findOneByLabel($entry['location']);

                    if ($mission && $location) {
                        $newDate = new DateTime($entry['date']);
                        $newEntry
                            ->setMission($mission)
                            ->setDate($newDate)
                            ->setQuantity($entry['quantity'])
                            ->setOperator($nomadUser)
                            ->setLocation($location);

                        if ($entry['is_ref']) {
                            $refArticle = $this->referenceArticleRepository->findOneByReference($entry['reference']);
                            $newEntry->setRefArticle($refArticle);
                            if ($newEntry->getQuantity() !== $refArticle->getQuantiteStock()) {
                                $newEntry->setAnomaly(true);
                            } else {
                                $refArticle->setDateLastInventory($newDate);
                                $newEntry->setAnomaly(false);
                            }
                            $em->flush();
                        } else {
                            $article = $this->articleRepository->findOneByReference($entry['reference']);
                            $newEntry->setArticle($article);

                            if ($newEntry->getQuantity() !== $article->getQuantite()) {
                                $newEntry->setAnomaly(true);
                            } else {
                                $newEntry->setAnomaly(false);
                            }
                            $em->flush();
                        }
                        $em->persist($newEntry);
                        $em->flush();
                    }
                    $numberOfRowsInserted++;
                }
                $s = $numberOfRowsInserted > 1 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
                    "Aucune saisie d'inventaire à synchroniser." : $numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }


    private function getDataArray($user)
    {
        $userTypes = [];
        foreach ($user->getTypes() as $type) {
            $userTypes[] = $type->getId();
        }

        $refAnomalies = $this->inventoryEntryRepository->getAnomaliesOnRef();
        $artAnomalies = $this->inventoryEntryRepository->getAnomaliesOnArt();

        $articles = $this->articleRepository->getIdRefLabelAndQuantity();
        $articlesRef = $this->referenceArticleRepository->getIdRefLabelAndQuantityByTypeQuantite(ReferenceArticle::TYPE_QUANTITE_REFERENCE);

        $articlesPrepa = $this->articleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);
        $refArticlesPrepa = $this->referenceArticleRepository->getByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);

        // get article linked to a ReferenceArticle where type_quantite === 'article'
        $articlesPrepaByRefArticle = $this->articleRepository->getRefArticleByPreparationStatutLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user);

        $articlesLivraison = $this->articleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);
        $refArticlesLivraison = $this->referenceArticleRepository->getByLivraisonStatutLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user);

        $articlesCollecte = $this->articleRepository->getByCollecteStatutLabelAndWithoutOtherUser(OrdreCollecte::STATUT_A_TRAITER, $user);
        $refArticlesCollecte = $this->referenceArticleRepository->getByCollecteStatutLabelAndWithoutOtherUser(OrdreCollecte::STATUT_A_TRAITER, $user);

        $articlesInventory = $this->inventoryMissionRepository->getCurrentMissionArticlesNotTreated();
        $refArticlesInventory = $this->inventoryMissionRepository->getCurrentMissionRefNotTreated();

        $manutentions = $this->manutentionRepository->findByStatut($this->statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::MANUTENTION, Manutention::STATUT_A_TRAITER));

        $data = [
            'emplacements' => $this->emplacementRepository->getIdAndNom(),
            'articles' => array_merge($articles, $articlesRef),
            'preparations' => $this->preparationRepository->getByStatusLabelAndUser(Preparation::STATUT_A_TRAITER, Preparation::STATUT_EN_COURS_DE_PREPARATION, $user, $userTypes),
            'articlesPrepa' => array_merge($articlesPrepa, $refArticlesPrepa),
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle,
            'livraisons' => $this->livraisonRepository->getByStatusLabelAndWithoutOtherUser(Livraison::STATUT_A_TRAITER, $user),
            'articlesLivraison' => array_merge($articlesLivraison, $refArticlesLivraison),
            'collectes' => $this->ordreCollecteRepository->getByStatutLabelAndUser(OrdreCollecte::STATUT_A_TRAITER, $user),
            'articlesCollecte' => array_merge($articlesCollecte, $refArticlesCollecte),
            'inventoryMission' => array_merge($articlesInventory, $refArticlesInventory),
            'manutentions' => $manutentions,
            'anomalies' => array_merge($refAnomalies, $artAnomalies)
        ];

        return $data;
    }

    /**
     * @Rest\Post("/api/getData", name= "api-get-data")
     */
    public function getData(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {
                $httpCode = Response::HTTP_OK;
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data'] = $this->getDataArray($nomadUser);
            } else {
                $httpCode = Response::HTTP_UNAUTHORIZED;
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['message'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            return new JsonResponse($this->successDataMsg, $httpCode);
        }
    }

    private function apiKeyGenerator()
    {
        $key = md5(microtime() . rand());
        return $key;
    }

    /**
     * @Rest\Get("/api/nomade-versions")
     */
    public function getAvailableVersionsAction() {
        return new JsonResponse($this->getParameter('nomade_versions') ?? '*');
    }

    /**
     * @Rest\Post("/api/treatAnomalies", name= "api-treat-anomalies-inv")
     * @Rest\Get("/api/treatAnomalies")
     */
    public function treatAnomalies(Request $request)
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = new Response();
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');

            if ($nomadUser = $this->utilisateurRepository->findOneByApiKey($data['apiKey'])) {

                $numberOfRowsInserted = 0;

                foreach ($data['anomalies'] as $anomaly) {
                    $this->inventoryService->doTreatAnomaly($anomaly['id'], $anomaly['reference'], $anomaly['is_ref'], $anomaly['quantity'], $anomaly['comment'], $nomadUser);
                    $numberOfRowsInserted++;
                }

                $s = $numberOfRowsInserted > 1 ? 's' : '';
                $this->successDataMsg['success'] = true;
                $this->successDataMsg['data']['status'] = ($numberOfRowsInserted === 0) ?
                    "Aucune anomalie d'inventaire à synchroniser." : $numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s;
            } else {
                $this->successDataMsg['success'] = false;
                $this->successDataMsg['msg'] = "Vous n'avez pas pu être authentifié. Veuillez vous reconnecter.";
            }

            $response->setContent(json_encode($this->successDataMsg));
            return $response;
        }
    }

}
