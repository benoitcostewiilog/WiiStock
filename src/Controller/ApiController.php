<?php

namespace App\Controller;

use App\Annotation as Wii;
use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Nature;
use App\Entity\Pack;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\InventoryEntry;
use App\Entity\InventoryMission;
use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Handling;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\ParametrageGlobal;
use App\Entity\TrackingMovement;
use App\Entity\OrdreCollecte;
use App\Entity\DispatchPack;
use App\Entity\Attachment;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\Translation;
use App\Entity\Type;
use App\Entity\Utilisateur;

use WiiCommon\Helper\Stream;

use App\Exceptions\ArticleNotAvailableException;
use App\Exceptions\RequestNeedToBeProcessedException;
use App\Exceptions\NegativeQuantityException;

use App\Repository\ArticleRepository;
use App\Repository\TrackingMovementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Service\DispatchService;
use App\Service\AttachmentService;
use App\Service\DemandeLivraisonService;
use App\Service\ExceptionLoggerService;
use App\Service\GroupService;
use App\Service\InventoryService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\HandlingService;
use App\Service\MouvementStockService;
use App\Service\StatusService;
use App\Service\TrackingMovementService;
use App\Service\NatureService;
use App\Service\PreparationsManagerService;
use App\Service\OrdreCollecteService;
use App\Service\TransferOrderService;
use App\Service\UserService;
use App\Service\FreeFieldService;

use DateTimeInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use DateTime;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;


/**
 * Class ApiController
 * @package App\Controller
 */
class ApiController extends AbstractFOSRestController
{

    /** @var Utilisateur|null */
    private $user;

    public function getUser(): Utilisateur
    {
        return $this->user;
    }

    public function setUser(Utilisateur $user)
    {
        $this->user = $user;
    }

    /**
     * @Rest\Post("/api/api-key", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestVersionChecked()
     */
    public function postApiKey(Request $request,
                               EntityManagerInterface $entityManager,
                               UserService $userService)
    {

        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $mobileKey = $request->request->get('loginKey');

        $loggedUser = $utilisateurRepository->findOneBy(['mobileLoginKey' => $mobileKey, 'status' => true]);
        $data = [];

        if (!empty($loggedUser)) {
            $apiKey = $this->apiKeyGenerator();
            $loggedUser->setApiKey($apiKey);
            $entityManager->flush();

            $rights = $this->getMenuRights($loggedUser, $userService);
            $channels = Stream::from($rights)
                ->filter(fn($val, $key) => $val && in_array($key, ["stock", "tracking", "group", "ungroup", "demande", "notifications"]))
                ->takeKeys()
                ->map(fn($right) => $_SERVER["APP_INSTANCE"] . "-" . $right)
                ->toArray();

            if (in_array($_SERVER["APP_INSTANCE"] . "-stock" , $channels)) {
                Stream::from($loggedUser->getDeliveryTypes())
                    ->each(function(Type $deliveryType) use (&$channels) {
                        $channels[] = $_SERVER["APP_INSTANCE"] . "-stock-delivery-" . $deliveryType->getId();
                    });
            }
            if (in_array($_SERVER["APP_INSTANCE"] . "-tracking" , $channels)) {
                Stream::from($loggedUser->getDispatchTypes())
                    ->each(function(Type $dispatchType) use (&$channels) {
                        $channels[] = $_SERVER["APP_INSTANCE"] . "-tracking-dispatch-" . $dispatchType->getId();
                    });
            }
            if (in_array($_SERVER["APP_INSTANCE"] . "-demande" , $channels)) {
                Stream::from($loggedUser->getHandlingTypes())
                    ->each(function(Type $handlingType) use (&$channels) {
                        $channels[] = $_SERVER["APP_INSTANCE"] . "-demande-handling-" . $handlingType->getId();
                    });
            }

            $data['success'] = true;
            $data['data'] = [
                'apiKey' => $apiKey,
                'notificationChannels' => $channels,
                'rights' => $rights,
                'username' => $loggedUser->getUsername(),
                'userId' => $loggedUser->getId()
            ];
        } else {
            $data['success'] = false;
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Get("/api/ping", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestVersionChecked()
     */
    public function ping()
    {
        $response = new JsonResponse(['success' => true]);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET');
        return $response;
    }

    /**
     * @Rest\Post("/api/mouvements-traca", name="api-post-mouvements-traca", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postTrackingMovement(Request $request,
                                         MailerService $mailerService,
                                         MouvementStockService $mouvementStockService,
                                         TrackingMovementService $trackingMovementService,
                                         ExceptionLoggerService $exceptionLoggerService,
                                         FreeFieldService $freeFieldService,
                                         AttachmentService $attachmentService,
                                         EntityManagerInterface $entityManager)
    {
        $successData = [];
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST');

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;
        $mouvementsNomade = json_decode($request->request->get('mouvements'), true);
        $finishMouvementTraca = [];
        $successData['data'] = [
            'errors' => []
        ];

        $emptyGroups = [];

        foreach ($mouvementsNomade as $index => $mvt) {
            $invalidLocationTo = '';
            try {
                $entityManager->transactional(function ()
                use (
                    $mailerService,
                    $freeFieldService,
                    $mouvementStockService,
                    &$numberOfRowsInserted,
                    $mvt,
                    $nomadUser,
                    $request,
                    $attachmentService,
                    $index,
                    &$invalidLocationTo,
                    &$finishMouvementTraca,
                    $entityManager,
                    $exceptionLoggerService,
                    $trackingMovementService,
                    $emptyGroups
                ) {
                    $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                    $articleRepository = $entityManager->getRepository(Article::class);
                    $statutRepository = $entityManager->getRepository(Statut::class);
                    $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
                    $packRepository = $entityManager->getRepository(Pack::class);

                    $mouvementTraca1 = $trackingMovementRepository->findOneByUniqueIdForMobile($mvt['date']);
                    if (!isset($mouvementTraca1)) {
                        $options = [
                            'commentaire' => null,
                            'mouvementStock' => null,
                            'fileBag' => null,
                            'from' => null,
                            'uniqueIdForMobile' => $mvt['date'],
                            'entityManager' => $entityManager,
                        ];
                        $location = $emplacementRepository->findOneBy(['label' => $mvt['ref_emplacement']]);
                        $type = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::MVT_TRACA, $mvt['type']);

                        // création de l'emplacement s'il n'existe pas
                        if (!$location) {
                            $location = new Emplacement();
                            $location->setLabel($mvt['ref_emplacement']);
                            $entityManager->persist($location);
                        }

                        $dateArray = explode('_', $mvt['date']);

                        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateArray[0]);

                        // set mouvement de stock
                        if ($mvt['fromStock'] ?? false) {
                            if ($type->getNom() === TrackingMovement::TYPE_PRISE) {
                                $articles = $articleRepository->findArticleByBarCodeAndLocation($mvt['ref_article'], $mvt['ref_emplacement']);
                                /** @var Article|null $article */
                                $article = count($articles) > 0 ? $articles[0] : null;
                                if (!isset($article)) {
                                    $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
                                    $references = $referenceArticleRepository->findReferenceByBarCodeAndLocation($mvt['ref_article'], $mvt['ref_emplacement']);
                                    /** @var ReferenceArticle|null $article */
                                    $article = count($references) > 0 ? $references[0] : null;
                                }

                                if (isset($article)) {
                                    $quantiteMouvement = ($article instanceof Article)
                                        ? $article->getQuantite()
                                        : $article->getQuantiteStock(); // ($article instanceof ReferenceArticle)

                                    $newMouvement = $mouvementStockService->createMouvementStock($nomadUser, $location, $quantiteMouvement, $article, MouvementStock::TYPE_TRANSFER);
                                    $options['mouvementStock'] = $newMouvement;
                                    $options['quantity'] = $newMouvement->getQuantity();
                                    $entityManager->persist($newMouvement);

                                    $configStatus = ($article instanceof Article)
                                        ? [Article::CATEGORIE, Article::STATUT_EN_TRANSIT]
                                        : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_INACTIF];

                                    $status = $statutRepository->findOneByCategorieNameAndStatutCode($configStatus[0], $configStatus[1]);
                                    $article->setStatut($status);
                                }
                            }
                            else { // MouvementTraca::TYPE_DEPOSE
                                $mouvementTracaPrises = $trackingMovementRepository->findLastTakingNotFinished($mvt['ref_article']);
                                /** @var TrackingMovement|null $mouvementTracaPrise */
                                $mouvementTracaPrise = count($mouvementTracaPrises) > 0 ? $mouvementTracaPrises[0] : null;
                                if (isset($mouvementTracaPrise)) {
                                    $mouvementStockPrise = $mouvementTracaPrise->getMouvementStock();
                                    $article = $mouvementStockPrise->getArticle()
                                        ?: $mouvementStockPrise->getRefArticle();

                                    $collecteOrder = $mouvementStockPrise->getCollecteOrder();
                                    if (isset($collecteOrder)
                                        && ($article instanceof ReferenceArticle)
                                        && $article->getEmplacement()
                                        && ($article->getEmplacement()->getId() !== $location->getId())) {
                                        $invalidLocationTo = ($article->getEmplacement() ? $article->getEmplacement()->getLabel() : '');
                                        throw new Exception(TrackingMovementService::INVALID_LOCATION_TO);
                                    } else {
                                        $options['mouvementStock'] = $mouvementStockPrise;
                                        $options['quantity'] = $mouvementStockPrise->getQuantity();
                                        $mouvementStockService->finishMouvementStock($mouvementStockPrise, $date, $location);

                                        $configStatus = ($article instanceof Article)
                                            ? [Article::CATEGORIE, Article::STATUT_ACTIF]
                                            : [ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF];

                                        $status = $statutRepository->findOneByCategorieNameAndStatutCode($configStatus[0], $configStatus[1]);
                                        $article
                                            ->setStatut($status)
                                            ->setEmplacement($location);

                                        // we update quantity if it's reference article from collecte
                                        if (isset($collecteOrder) && ($article instanceof ReferenceArticle)) {
                                            $article->setQuantiteStock(($article->getQuantiteStock() ?? 0) + $mouvementStockPrise->getQuantity());
                                        }
                                    }
                                }
                            }
                        }
                        else {
                            $options['natureId'] = $mvt['nature_id'] ?? null;
                            $options['quantity'] = $mvt['quantity'] ?? null;
                        }

                        if (!empty($mvt['comment'])) {
                            $options['commentaire'] = $mvt['comment'];
                        }

                        $signatureFile = $request->files->get("signature_$index");
                        $photoFile = $request->files->get("photo_$index");
                        if (!empty($signatureFile) || !empty($photoFile)) {
                            $options['fileBag'] = [];
                            if (!empty($signatureFile)) {
                                $options['fileBag'][] = $signatureFile;
                            }

                            if (!empty($photoFile)) {
                                $options['fileBag'][] = $photoFile;
                            }
                        }
                        $createdMvt = $trackingMovementService->createTrackingMovement(
                            $mvt['ref_article'],
                            $location,
                            $nomadUser,
                            $date,
                            true,
                            $mvt['finished'],
                            $type,
                            $options,
                        );
                        $associatedPack = $createdMvt->getPack();

                        if ($associatedPack) {
                            $associatedGroup = $associatedPack->getParent();

                            if ($associatedGroup) {
                                $associatedGroup->removeChild($associatedPack);
                                if ($associatedGroup->getChildren()->isEmpty()) {
                                    $emptyGroups[] = $associatedGroup->getCode();
                                }
                            }
                        }

                        $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                        $entityManager->persist($createdMvt);
                        $numberOfRowsInserted++;
                        if ((!isset($mvt['fromStock']) || !$mvt['fromStock'])
                            && $mvt['freeFields']) {
                            $givenFreeFields = json_decode($mvt['freeFields'], true);
                            $smartFreeFields = array_reduce(
                                array_keys($givenFreeFields),
                                function (array $acc, $id) use ($givenFreeFields) {
                                    if (gettype($id) === 'integer' || ctype_digit($id)) {
                                        $acc[(int)$id] = $givenFreeFields[$id];
                                    }
                                    return $acc;
                                },
                                []
                            );
                            if (!empty($smartFreeFields)) {
                                $freeFieldService->manageFreeFields($createdMvt, $smartFreeFields, $entityManager);
                            }
                        }

                        // envoi de mail si c'est une dépose + le colis existe + l'emplacement est un point de livraison
                        if ($location) {
                            $isDepose = ($mvt['type'] === TrackingMovement::TYPE_DEPOSE);
                            $colis = $packRepository->findOneBy(['code' => $mvt['ref_article']]);

                            if ($isDepose
                                && $colis
                                && $colis->getArrivage()
                                && $location->getIsDeliveryPoint()) {
                                $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                                $fournisseur = $fournisseurRepository->findOneByColis($colis);
                                $arrivage = $colis->getArrivage();
                                $destinataire = $arrivage->getDestinataire();
                                if ($destinataire) {
                                    $mailerService->sendMail(
                                        'FOLLOW GT // Dépose effectuée',
                                        $this->renderView(
                                            'mails/contents/mailDeposeTraca.html.twig',
                                            [
                                                'title' => 'Votre colis a été livré.',
                                                'colis' => $colis->getCode(),
                                                'emplacement' => $location,
                                                'fournisseur' => $fournisseur ? $fournisseur->getNom() : '',
                                                'date' => $date,
                                                'operateur' => $nomadUser->getUsername(),
                                                'pjs' => $arrivage->getAttachments()
                                            ]
                                        ),
                                        $destinataire
                                    );
                                }
                            }
                        }

                        if ($type->getNom() === TrackingMovement::TYPE_DEPOSE) {
                            $finishMouvementTraca[] = $mvt['ref_article'];
                        }
                    }
                });
            } catch (Throwable $throwable) {
                if (!$entityManager->isOpen()) {
                    /** @var EntityManagerInterface $entityManager */
                    $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                    $entityManager->clear();
                    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
                    $nomadUser = $utilisateurRepository->findOneByApiKey($request->request->get("apiKey"));
                }

                if ($throwable->getMessage() === TrackingMovementService::INVALID_LOCATION_TO) {
                    $successData['data']['errors'][$mvt['ref_article']] = ($mvt['ref_article'] . " doit être déposé sur l'emplacement \"$invalidLocationTo\"");
                } else if ($throwable->getMessage() === Pack::PACK_IS_GROUP) {
                    $successData['data']['errors'][$mvt['ref_article']] = 'Le colis scanné est un groupe';
                } else {
                    $exceptionLoggerService->sendLog($throwable, $request);
                    $successData['data']['errors'][$mvt['ref_article']] = 'Une erreur s\'est produite lors de l\'enregistrement de ' . $mvt['ref_article'];
                }
            }
        }

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        // Pour tous les mouvement de prise envoyés, on les marques en fini si un mouvement de dépose a été donné
        foreach ($mouvementsNomade as $mvt) {
            /** @var TrackingMovement $mouvementTracaPriseToFinish */
            $mouvementTracaPriseToFinish = $trackingMovementRepository->findOneByUniqueIdForMobile($mvt['date']);

            if (isset($mouvementTracaPriseToFinish)) {
                $trackingPack = $mouvementTracaPriseToFinish->getPack();
                if ($trackingPack) {
                    $packCode = $trackingPack->getCode();
                    if (($mouvementTracaPriseToFinish->getType()->getNom() === TrackingMovement::TYPE_PRISE) &&
                        in_array($packCode, $finishMouvementTraca) &&
                        !$mouvementTracaPriseToFinish->isFinished()) {
                        $mouvementTracaPriseToFinish->setFinished((bool)$mvt['finished']);
                    }
                }
            }
        }
        $entityManager->flush();

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $successData['success'] = true;
        $successData['data']['status'] = ($numberOfRowsInserted === 0)
            ? 'Aucun mouvement à synchroniser.'
            : ($numberOfRowsInserted . ' mouvement' . $s . ' synchronisé' . $s);
        $successData['data']['movementCounter'] = $numberOfRowsInserted;

        if (!empty($emptyGroups)) {
            $successData['data']['emptyGroups'] = $emptyGroups;
        }

        $response->setContent(json_encode($successData));
        return $response;
    }

    /**
     * @Rest\Post("/api/beginPrepa", name="api-begin-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function beginPrepa(Request $request,
                               EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $id = $request->request->get('id');
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $preparation = $preparationRepository->find($id);
        $data = [];

        if ($preparation->getStatut()->getNom() == Preparation::STATUT_A_TRAITER ||
            $preparation->getUtilisateur() === $nomadUser) {
            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette préparation a déjà été prise en charge par un opérateur.";
            $data['data'] = [];
        }

        return $this->json($data);
    }

    /**
     * @Rest\Post("/api/finishPrepa", name="api-finish-prepa", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishPrepa(Request $request,
                                ExceptionLoggerService $exceptionLoggerService,
                                LivraisonsManagerService $livraisonsManager,
                                PreparationsManagerService $preparationsManager,
                                EntityManagerInterface $entityManager)
    {
        $insertedPrepasIds = [];
        $statusCode = Response::HTTP_OK;

        $nomadUser = $this->getUser();

        $articleRepository = $entityManager->getRepository(Article::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);

        $resData = ['success' => [], 'errors' => [], 'data' => []];

        $preparations = json_decode($request->request->get('preparations'), true);

        // on termine les préparations
        // même comportement que LivraisonController.new()
        foreach ($preparations as $preparationArray) {
            $preparation = $preparationRepository->find($preparationArray['id']);
            if ($preparation) {
                // if it has not been begun
                try {
                    $dateEnd = DateTime::createFromFormat(DateTimeInterface::ATOM, $preparationArray['date_end']);
                    // flush auto at the end
                    $entityManager->transactional(function () use (
                        &$insertedPrepasIds,
                        $preparationsManager,
                        $livraisonsManager,
                        $preparationArray,
                        $preparation,
                        $nomadUser,
                        $dateEnd,
                        $entityManager
                    ) {

                        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                        $articleRepository = $entityManager->getRepository(Article::class);
                        $ligneArticlePreparationRepository = $entityManager->getRepository(LigneArticlePreparation::class);
                        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

                        $preparationsManager->setEntityManager($entityManager);
                        $mouvementsNomade = $preparationArray['mouvements'];
                        $totalQuantitiesWithRef = [];
                        $livraison = $livraisonsManager->createLivraison($dateEnd, $preparation, $entityManager);

                        foreach ($mouvementsNomade as $mouvementNomade) {
                            if (!$mouvementNomade['is_ref'] && $mouvementNomade['selected_by_article']) {
                                /** @var Article $article */
                                $article = $articleRepository->findOneByReference($mouvementNomade['reference']);
                                $refArticle = $article->getArticleFournisseur()->getReferenceArticle();
                                if (!isset($totalQuantitiesWithRef[$refArticle->getReference()])) {
                                    $totalQuantitiesWithRef[$refArticle->getReference()] = 0;
                                }
                                $totalQuantitiesWithRef[$refArticle->getReference()] += $mouvementNomade['quantity'];
                            }
                            $preparationsManager->treatMouvementQuantities($mouvementNomade, $preparation);
                        }

                        $articlesToKeep = $preparationsManager->createMouvementsPrepaAndSplit($preparation, $nomadUser, $entityManager);

                        foreach ($mouvementsNomade as $mouvementNomade) {
                            $emplacement = $emplacementRepository->findOneBy(['label' => $mouvementNomade['location']]);
                            $preparationsManager->createMouvementLivraison(
                                $mouvementNomade['quantity'],
                                $nomadUser,
                                $livraison,
                                $mouvementNomade['is_ref'],
                                $mouvementNomade['reference'],
                                $preparation,
                                $mouvementNomade['selected_by_article'],
                                $emplacement
                            );
                        }

                        foreach ($totalQuantitiesWithRef as $ref => $quantity) {
                            $refArticle = $referenceArticleRepository->findOneByReference($ref);
                            $ligneArticle = $ligneArticlePreparationRepository->findOneByRefArticleAndDemande($refArticle, $preparation->getDemande());
                            $preparationsManager->deleteLigneRefOrNot($ligneArticle);
                        }
                        $emplacementPrepa = $emplacementRepository->findOneBy(['label' => $preparationArray['emplacement']]);
                        $insertedPreparation = $preparationsManager->treatPreparation($preparation, $nomadUser, $emplacementPrepa, $articlesToKeep, $entityManager);

                        if ($insertedPreparation) {
                            $insertedPrepasIds[] = $insertedPreparation->getId();
                        }

                        if ($emplacementPrepa) {
                            $preparationsManager->closePreparationMouvement($preparation, $dateEnd, $emplacementPrepa);
                        } else {
                            throw new Exception(PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                        }

                        $entityManager->flush();

                        $preparationsManager->updateRefArticlesQuantities($preparation, $entityManager);
                    });

                    $resData['success'][] = [
                        'numero_prepa' => $preparation->getNumero(),
                        'id_prepa' => $preparation->getId()
                    ];
                } catch (Throwable $throwable) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        /** @var EntityManagerInterface $entityManager */
                        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        $preparationsManager->setEntityManager($entityManager);
                    }

                    $message = (
                    ($throwable instanceof NegativeQuantityException) ? "Une quantité en stock d\'un article est inférieure à sa quantité prélevée" :
                        (($throwable->getMessage() === PreparationsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                            (($throwable->getMessage() === PreparationsManagerService::ARTICLE_ALREADY_SELECTED) ? "L'article n'est pas sélectionnable" :
                                false))
                    );

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable, $request);
                    }

                    $resData['errors'][] = [
                        'numero_prepa' => $preparation->getNumero(),
                        'id_prepa' => $preparation->getId(),
                        'message' => $message ?: 'Une erreur est survenue'
                    ];
                }
            }
        }

        if (!empty($insertedPrepasIds)) {
            $resData['data']['preparations'] = Stream::from($preparationRepository->getMobilePreparations($nomadUser, $insertedPrepasIds))
                ->map(function ($preparationArray) {
                    if (!empty($preparationArray['comment'])) {
                        $preparationArray['comment'] = substr(strip_tags($preparationArray['comment']), 0, 200);
                    }
                    return $preparationArray;
                })
                ->toArray();
            $resData['data']['articlesPrepa'] = $this->getArticlesPrepaArrays($insertedPrepasIds, true);
            $resData['data']['articlesPrepaByRefArticle'] = $articleRepository->getArticlePrepaForPickingByUser($nomadUser, $insertedPrepasIds);
        }

        $preparationsManager->removeRefMouvements();
        $entityManager->flush();

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/beginLivraison", name="api-begin-livraison", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function beginLivraison(Request $request, EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $livraisonRepository = $entityManager->getRepository(Livraison::class);

        $id = $request->request->get('id');
        $livraison = $livraisonRepository->find($id);

        $data = [];

        if ($livraison->getStatut()->getNom() == Livraison::STATUT_A_TRAITER &&
            (empty($livraison->getUtilisateur()) || $livraison->getUtilisateur() === $nomadUser)) {
            // modif de la livraison
            $livraison->setUtilisateur($nomadUser);

            $entityManager->flush();

            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette livraison a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/beginCollecte", name="api-begin-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function beginCollecte(Request $request,
                                  EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);

        $id = $request->request->get('id');
        $ordreCollecte = $ordreCollecteRepository->find($id);

        $data = [];

        if ($ordreCollecte->getStatut()->getNom() == OrdreCollecte::STATUT_A_TRAITER &&
            (empty($ordreCollecte->getUtilisateur()) || $ordreCollecte->getUtilisateur() === $nomadUser)) {
            // modif de la collecte
            $ordreCollecte->setUtilisateur($nomadUser);

            $entityManager->flush();

            $data['success'] = true;
        } else {
            $data['success'] = false;
            $data['msg'] = "Cette collecte a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/handlings", name="api-validate-handling", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postHandlings(Request $request,
                                  AttachmentService $attachmentService,
                                  EntityManagerInterface $entityManager,
                                  FreeFieldService $freeFieldService,
                                  StatusService $statusService,
                                  HandlingService $handlingService)
    {
        $nomadUser = $this->getUser();

        $handlingRepository = $entityManager->getRepository(Handling::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $data = [];

        $id = $request->request->get('id');
        /** @var Handling $handling */
        $handling = $handlingRepository->find($id);
        $oldStatus = $handling->getStatus();

        if (!$oldStatus || !$oldStatus->isTreated()) {
            $statusId = $request->request->get('statusId');
            $newStatus = $statusRepository->find($statusId);
            if (!empty($newStatus)) {
                $handling->setStatus($newStatus);
            }

            $commentaire = $request->request->get('comment');
            $treatmentDelay = $request->request->get('treatmentDelay');
            if (!empty($commentaire)) {
                $handling->setComment($handling->getComment() . "\n" . date('d/m/y H:i:s') . " - " . $nomadUser->getUsername() . " :\n" . $commentaire);
            }

            if (!empty($treatmentDelay)) {
                $handling->setTreatmentDelay($treatmentDelay);
            }

            $maxNbFilesSubmitted = 10;
            $fileCounter = 1;
            // upload of photo_1 to photo_10
            do {
                $photoFile = $request->files->get("photo_$fileCounter");
                if (!empty($photoFile)) {
                    $attachments = $attachmentService->createAttachements([$photoFile]);
                    if (!empty($attachments)) {
                        $handling->addAttachment($attachments[0]);
                        $entityManager->persist($attachments[0]);
                    }
                }
                $fileCounter++;
            } while (!empty($photoFile) && $fileCounter <= $maxNbFilesSubmitted);

            $freeFieldValuesStr = $request->request->get('freeFields', '{}');
            $freeFieldValuesStr = json_decode($freeFieldValuesStr, true);
            $freeFieldService->manageFreeFields($handling, $freeFieldValuesStr, $entityManager);

            if (!$handling->getValidationDate()
                && $newStatus) {
                if ($newStatus->isTreated()) {
                    $handling
                        ->setValidationDate(new DateTime('now'));
                }
                $handling->setTreatedByHandling($nomadUser);
            }
            $entityManager->flush();

            if ((!$oldStatus && $newStatus)
                || (
                    $oldStatus
                    && $newStatus
                    && ($oldStatus->getId() !== $newStatus->getId())
                )) {
                $viewHoursOnExpectedDate = !$parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME);
                $handlingService->sendEmailsAccordingToStatus($entityManager, $handling, $viewHoursOnExpectedDate);
            }

            $data['success'] = true;
            $data['state'] = $statusService->getStatusStateCode($handling->getStatus()->getState());
            $data['freeFields'] = json_encode($handling->getFreeFields());
        } else {
            $data['success'] = false;
            $data['message'] = "Cette demande de service a déjà été prise en charge par un opérateur.";
        }

        return new JsonResponse($data);
    }

    /**
     * @Rest\Post("/api/finishLivraison", name="api-finish-livraison", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishLivraison(Request $request,
                                    ExceptionLoggerService $exceptionLoggerService,
                                    EntityManagerInterface $entityManager,
                                    LivraisonsManagerService $livraisonsManager)
    {
        $nomadUser = $this->getUser();

        $statusCode = Response::HTTP_OK;
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $livraisons = json_decode($request->request->get('livraisons'), true);
        $resData = ['success' => [], 'errors' => []];

        // on termine les livraisons
        // même comportement que LivraisonController.finish()
        foreach ($livraisons as $livraisonArray) {
            $livraison = $livraisonRepository->find($livraisonArray['id']);

            if ($livraison) {
                $dateEnd = DateTime::createFromFormat(DateTimeInterface::ATOM, $livraisonArray['date_end']);
                $location = $emplacementRepository->findOneBy(['label' => $livraisonArray['location']]);
                try {
                    if ($location) {
                        // flush auto at the end
                        $entityManager->transactional(function () use ($livraisonsManager, $entityManager, $nomadUser, $livraison, $dateEnd, $location) {
                            $livraisonsManager->setEntityManager($entityManager);
                            $livraisonsManager->finishLivraison($nomadUser, $livraison, $dateEnd, $location);
                            $entityManager->flush();
                        });

                        $resData['success'][] = [
                            'numero_livraison' => $livraison->getNumero(),
                            'id_livraison' => $livraison->getId()
                        ];
                    } else {
                        throw new Exception(LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION);
                    }
                } catch (Throwable $throwable) {
                    // we create a new entity manager because transactional() can call close() on it if transaction failed
                    if (!$entityManager->isOpen()) {
                        $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                        $livraisonsManager->setEntityManager($entityManager);
                    }

                    $message = (
                    ($throwable->getMessage() === LivraisonsManagerService::MOUVEMENT_DOES_NOT_EXIST_EXCEPTION) ? "L'emplacement que vous avez sélectionné n'existe plus." :
                        (($throwable->getMessage() === LivraisonsManagerService::LIVRAISON_ALREADY_BEGAN) ? "La livraison a déjà été commencée" :
                            false)
                    );

                    if (!$message) {
                        $exceptionLoggerService->sendLog($throwable, $request);
                    }

                    $resData['errors'][] = [
                        'numero_livraison' => $livraison->getNumero(),
                        'id_livraison' => $livraison->getId(),

                        'message' => $message ?: 'Une erreur est survenue'
                    ];
                }

                $entityManager->flush();
            }
        }

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/group-trackings/{trackingMode}", name="api_post_pack_groups", condition="request.isXmlHttpRequest()", requirements={"trackingMode": "picking|drop"})
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function postGroupedTracking(Request $request,
                                        AttachmentService $attachmentService,
                                        EntityManagerInterface $entityManager,
                                        TrackingMovementService $trackingMovementService,
                                        string $trackingMode): JsonResponse {

        /** @var Utilisateur $nomadUser */
        $operator = $this->getUser();
        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);

        $movementsStr = $request->request->get('mouvements');
        $movements = json_decode($movementsStr, true);

        $finishedMovements = ($trackingMode === 'drop');
        $movementType = $trackingMode === 'drop' ? TrackingMovement::TYPE_DEPOSE : TrackingMovement::TYPE_PRISE;

        $groupsArray = Stream::from($movements)
            ->map(function($movement) {
                $date = explode('+', $movement['date']);
                $date = $date[0] ?? $movement['date'];
                return [
                    'code' => $movement['ref_article'],
                    'location' => $movement['ref_emplacement'],
                    'nature_id' => $movement['nature_id'],
                    'date' => new DateTime($date ?? 'now'),
                    'type' => $movement['type']
                ];
            })
            ->toArray();

        $res = [
            'success' => true,
            'finishedMovements' => []
        ];

        try {
            foreach ($groupsArray as $groupIndex => $serializedGroup) {
                $newMovements = [];

                /** @var Pack $parent */
                $parent = $packRepository->findOneBy(['code' => $serializedGroup['code']]);
                if ($parent && !$parent->getChildren()->isEmpty()) {
                    if (isset($serializedGroup['nature_id'])) {
                        $nature = $natureRepository->find($serializedGroup['nature_id']);
                        $parent->setNature($nature);
                    }

                    $location = $locationRepository->findOneBy(['label' => $serializedGroup['location']]);

                    $options = ['disableUngrouping' => true];

                    if ($finishedMovements) {
                        $res['finishedMovements'][] = $trackingMovementService->finishTrackingMovement($parent->getLastTracking());
                    }

                    /** @var Pack $child */
                    foreach ($parent->getChildren() as $child) {
                        if ($finishedMovements) {
                            $res['finishedMovements'][] = $trackingMovementService->finishTrackingMovement($child->getLastTracking());
                        }

                        $trackingMovement = $trackingMovementService->createTrackingMovement(
                            $child,
                            $location,
                            $operator,
                            $serializedGroup['date'],
                            true,
                            $finishedMovements,
                            $movementType,
                            array_merge(['parent' => $parent], $options)
                        );

                        $newMovements[] = $trackingMovement;

                        $entityManager->persist($trackingMovement);
                        $trackingMovementService->persistSubEntities($entityManager, $trackingMovement);
                    }

                    $trackingMovement = $trackingMovementService->createTrackingMovement(
                        $parent,
                        $location,
                        $operator,
                        $serializedGroup['date'],
                        true,
                        $finishedMovements,
                        $movementType,
                        $options
                    );

                    $newMovements[] = $trackingMovement;

                    $entityManager->persist($trackingMovement);
                    $trackingMovementService->persistSubEntities($entityManager, $trackingMovement);

                    $signatureFile = $request->files->get("signature_$groupIndex");
                    $photoFile = $request->files->get("photo_$groupIndex");
                    $fileNames = [];
                    if (!empty($signatureFile)) {
                        $fileNames = array_merge($fileNames, $attachmentService->saveFile($signatureFile));
                    }
                    if (!empty($photoFile)) {
                        $fileNames = array_merge($fileNames, $attachmentService->saveFile($photoFile));
                    }

                    foreach ($newMovements as $movement) {
                        $attachments = $attachmentService->createAttachements($fileNames);
                        foreach ($attachments as $attachment) {
                            $entityManager->persist($attachment);
                            $movement->addAttachment($attachment);
                        }
                    }

                    $res['finishedMovements'] = Stream::from($res['finishedMovements'])
                        ->filter(fn($code) => $code)
                        ->unique()
                        ->values();
                }
            }

            $entityManager->flush();

            $res['tracking'] = $trackingMovementService->getMobileUserPicking($entityManager, $operator);
        }
        catch (Throwable $throwable) {
            $res['success'] = false;
            $res['message'] = "Une erreur est survenue lors de l'enregistrement d'un mouvement";
        }

        return $this->json($res);
    }

    /**
     * @Rest\Post("/api/finishCollecte", name="api-finish-collecte", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishCollecte(Request $request,
                                   ExceptionLoggerService $exceptionLoggerService,
                                   OrdreCollecteService $ordreCollecteService,
                                   EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $statusCode = Response::HTTP_OK;

        $resData = ['success' => [], 'errors' => [], 'data' => []];

        $collectes = json_decode($request->request->get('collectes'), true);

        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        // on termine les collectes
        foreach ($collectes as $collecteArray) {
            $collecte = $ordreCollecteRepository->find($collecteArray['id']);
            try {
                $entityManager->transactional(function ()
                use (
                    $entityManager,
                    $collecteArray,
                    $collecte,
                    $nomadUser,
                    &$resData,
                    $trackingMovementRepository,
                    $articleRepository,
                    $refArticlesRepository,
                    $ordreCollecteRepository,
                    $emplacementRepository,
                    $ordreCollecteService
                ) {
                    $ordreCollecteService->setEntityManager($entityManager);
                    $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $collecteArray['date_end']);

                    $newCollecte = $ordreCollecteService->finishCollecte($collecte, $nomadUser, $date, $collecteArray['mouvements'], true);
                    $entityManager->flush();

                    if (!empty($newCollecte)) {
                        $newCollecteId = $newCollecte->getId();
                        $newCollecteArray = $ordreCollecteRepository->getById($newCollecteId);

                        $articlesCollecte = $articleRepository->getByOrdreCollecteId($newCollecteId);
                        $refArticlesCollecte = $refArticlesRepository->getByOrdreCollecteId($newCollecteId);
                        $articlesCollecte = array_merge($articlesCollecte, $refArticlesCollecte);
                    }

                    $resData['success'][] = [
                        'numero_collecte' => $collecte->getNumero(),
                        'id_collecte' => $collecte->getId()
                    ];

                    $newTakings = $trackingMovementRepository->getPickingByOperatorAndNotDropped(
                        $nomadUser,
                        TrackingMovementRepository::MOUVEMENT_TRACA_STOCK,
                        [$collecte->getId()]
                    );

                    if (!empty($newTakings)) {
                        if (!isset($resData['data']['stockTakings'])) {
                            $resData['data']['stockTakings'] = [];
                        }
                        array_push(
                            $resData['data']['stockTakings'],
                            ...$newTakings
                        );
                    }

                    if (isset($newCollecteArray)) {
                        if (!isset($resData['data']['newCollectes'])) {
                            $resData['data']['newCollectes'] = [];
                        }
                        $resData['data']['newCollectes'][] = $newCollecteArray;
                    }

                    if (!empty($articlesCollecte)) {
                        if (!isset($resData['data']['articlesCollecte'])) {
                            $resData['data']['articlesCollecte'] = [];
                        }
                        array_push(
                            $resData['data']['articlesCollecte'],
                            ...$articlesCollecte
                        );
                    }
                });
            } catch (Throwable $throwable) {
                // we create a new entity manager because transactional() can call close() on it if transaction failed
                if (!$entityManager->isOpen()) {
                    $entityManager = EntityManager::Create($entityManager->getConnection(), $entityManager->getConfiguration());
                    $ordreCollecteService->setEntityManager($entityManager);

                    $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
                    $articleRepository = $entityManager->getRepository(Article::class);
                    $refArticlesRepository = $entityManager->getRepository(ReferenceArticle::class);
                    $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
                    $emplacementRepository = $entityManager->getRepository(Emplacement::class);
                }

                $user = $collecte->getUtilisateur() ? $collecte->getUtilisateur()->getUsername() : '';

                $message = (
                ($throwable instanceof ArticleNotAvailableException) ? ("Une référence de la collecte n'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.") :
                    (($throwable->getMessage() === OrdreCollecteService::COLLECTE_ALREADY_BEGUN) ? ("La collecte " . $collecte->getNumero() . " a déjà été effectuée (par " . $user . ").") :
                        (($throwable->getMessage() === OrdreCollecteService::COLLECTE_MOUVEMENTS_EMPTY) ? ("La collecte " . $collecte->getNumero() . " ne contient aucun article.") :
                            false))
                );

                if (!$message) {
                    $exceptionLoggerService->sendLog($throwable, $request);
                }

                $resData['errors'][] = [
                    'numero_collecte' => $collecte->getNumero(),
                    'id_collecte' => $collecte->getId(),

                    'message' => $message ?: 'Une erreur est survenue'
                ];
            }
        }

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Post("/api/valider-dl", name="api_validate_dl", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function checkAndValidateDL(Request $request,
                                       EntityManagerInterface $entityManager,
                                       DemandeLivraisonService $demandeLivraisonService,
                                       FreeFieldService $champLibreService): Response
    {
        $nomadUser = $this->getUser();

        $demandeArray = json_decode($request->request->get('demande'), true);
        $demandeArray['demandeur'] = $nomadUser;

        $freeFields = json_decode($demandeArray["freeFields"], true);

        if (is_array($freeFields)) {
            foreach ($freeFields as $key => $value) {
                $demandeArray[(int)$key] = $value;
            }
        }

        unset($demandeArray["freeFields"]);

        $responseAfterQuantitiesCheck = $demandeLivraisonService->checkDLStockAndValidate(
            $entityManager,
            $demandeArray,
            true,
            $champLibreService
        );

        $responseAfterQuantitiesCheck['nomadMessage'] = $responseAfterQuantitiesCheck['nomadMessage']
            ?? $responseAfterQuantitiesCheck['msg']
            ?? '';

        return new JsonResponse($responseAfterQuantitiesCheck);
    }

    /**
     * @Rest\Post("/api/addInventoryEntries", name="api-add-inventory-entry", condition="request.isXmlHttpRequest()")
     * @Rest\Get("/api/addInventoryEntries")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function addInventoryEntries(Request $request, EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $numberOfRowsInserted = 0;

        $entries = json_decode($request->request->get('entries'), true);
        $newAnomalies = [];

        foreach ($entries as $entry) {
            $mission = $inventoryMissionRepository->find($entry['id_mission']);
            $location = $emplacementRepository->findOneBy(['label' => $entry['location']]);

            $articleToInventory = $entry['is_ref']
                ? $referenceArticleRepository->findOneBy(['barCode' => $entry['bar_code']])
                : $articleRepository->findOneBy(['barCode' => $entry['bar_code']]);

            $criteriaInventoryEntry = ['mission' => $mission];

            if (isset($articleToInventory)) {
                if ($articleToInventory instanceof ReferenceArticle) {
                    $criteriaInventoryEntry['refArticle'] = $articleToInventory;
                } else { // ($articleToInventory instanceof Article)
                    $criteriaInventoryEntry['article'] = $articleToInventory;
                }
            }

            $inventoryEntry = $inventoryEntryRepository->findOneBy($criteriaInventoryEntry);

            // On inventorie l'article seulement si les infos sont valides et si aucun inventaire de l'article
            // n'a encore été fait sur cette mission
            if (isset($mission) &&
                isset($location) &&
                isset($articleToInventory) &&
                !isset($inventoryEntry)) {
                $newDate = new DateTime($entry['date']);
                $inventoryEntry = new InventoryEntry();
                $inventoryEntry
                    ->setMission($mission)
                    ->setDate($newDate)
                    ->setQuantity($entry['quantity'])
                    ->setOperator($nomadUser)
                    ->setLocation($location);

                if ($articleToInventory instanceof ReferenceArticle) {
                    $inventoryEntry->setRefArticle($articleToInventory);
                    $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantiteStock());
                } else {
                    $inventoryEntry->setArticle($articleToInventory);
                    $isAnomaly = ($inventoryEntry->getQuantity() !== $articleToInventory->getQuantite());
                }
                $inventoryEntry->setAnomaly($isAnomaly);

                if (!$isAnomaly) {
                    $articleToInventory->setDateLastInventory($newDate);
                }
                $entityManager->persist($inventoryEntry);

                if ($inventoryEntry->getAnomaly()) {
                    $newAnomalies[] = $inventoryEntry;
                }
                $numberOfRowsInserted++;
            }
        }
        $entityManager->flush();

        $newAnomaliesIds = array_map(
            function (InventoryEntry $inventory) {
                return $inventory->getId();
            },
            $newAnomalies
        );

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $data['success'] = true;
        $data['data']['status'] = ($numberOfRowsInserted === 0)
            ? "Aucune saisie d'inventaire à synchroniser."
            : ($numberOfRowsInserted . ' inventaire' . $s . ' synchronisé' . $s);
        $data['data']['anomalies'] = array_merge(
            $inventoryEntryRepository->getAnomaliesOnRef(true, $newAnomaliesIds),
            $inventoryEntryRepository->getAnomaliesOnArt(true, $newAnomaliesIds)
        );

        return $this->json($data);
    }

    /**
     * @Rest\Get("/api/demande-livraison-data", name="api_get_demande_livraison_data")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getDemandeLivraisonData(UserService $userService, EntityManagerInterface $entityManager): Response
    {
        $nomadUser = $this->getUser();

        $dataResponse = [];
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $httpCode = Response::HTTP_OK;
        $dataResponse['success'] = true;

        $rights = $this->getMenuRights($nomadUser, $userService);
        if ($rights['demande']) {
            $dataResponse['data'] = [
                'demandeLivraisonArticles' => $referenceArticleRepository->getByNeedsMobileSync(),
                'demandeLivraisonTypes' => array_map(function (Type $type) {
                    return [
                        'id' => $type->getId(),
                        'label' => $type->getLabel(),
                    ];
                }, $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]))
            ];
        } else {
            $dataResponse['data'] = [
                'demandeLivraisonArticles' => [],
                'demandeLivraisonTypes' => []
            ];
        }

        return new JsonResponse($dataResponse, $httpCode);
    }

    /**
     * @Rest\Post("/api/transfer/finish", name="transfer_finish")
     * @Rest\View()
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function finishTransfers(Request $request,
                                    TransferOrderService $transferOrderService,
                                    EntityManagerInterface $entityManager): Response
    {
        $nomadUser = $this->getUser();

        $dataResponse = [];
        $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);

        $httpCode = Response::HTTP_OK;
        $transferToTreat = json_decode($request->request->get('transfers'), true) ?: [];
        Stream::from($transferToTreat)
            ->each(function ($transferId) use ($transferOrderRepository, $transferOrderService, $nomadUser, $entityManager) {
                $transfer = $transferOrderRepository->find($transferId);
                $transferOrderService->finish($transfer, $nomadUser, $entityManager);
            });

        $entityManager->flush();
        $dataResponse['success'] = $transferToTreat;

        return new JsonResponse($dataResponse, $httpCode);
    }

    private function getDataArray(Utilisateur $user,
                                  UserService $userService,
                                  TrackingMovementService $trackingMovementService,
                                  NatureService $natureService,
                                  Request $request,
                                  EntityManagerInterface $entityManager)
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);
        $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
        $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
        $preparationRepository = $entityManager->getRepository(Preparation::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $translationsRepository = $entityManager->getRepository(Translation::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $handlingRepository = $entityManager->getRepository(Handling::class);
        $attachmentRepository = $entityManager->getRepository(Attachment::class);
        $transferOrderRepository = $entityManager->getRepository(TransferOrder::class);
        $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $rights = $this->getMenuRights($user, $userService);

        $status = $statutRepository->getMobileStatus($rights['tracking'], $rights['demande']);

        if ($rights['inventoryManager']) {
            $refAnomalies = $inventoryEntryRepository->getAnomaliesOnRef(true);
            $artAnomalies = $inventoryEntryRepository->getAnomaliesOnArt(true);
        }

        if ($rights['stock']) {
            // livraisons
            $livraisons = Stream::from($livraisonRepository->getMobileDelivery($user))
                ->map(function ($deliveryArray) {
                    if (!empty($deliveryArray['comment'])) {
                        $deliveryArray['comment'] = substr(strip_tags($deliveryArray['comment']), 0, 200);
                    }
                    return $deliveryArray;
                })
                ->toArray();

            $livraisonsIds = Stream::from($livraisons)
                ->map(function ($livraisonArray) {
                    return $livraisonArray['id'];
                })
                ->toArray();

            $articlesLivraison = $articleRepository->getByLivraisonsIds($livraisonsIds);
            $refArticlesLivraison = $referenceArticleRepository->getByLivraisonsIds($livraisonsIds);

            /// preparations
            $preparations = Stream::from($preparationRepository->getMobilePreparations($user))
                ->map(function ($preparationArray) {
                    if (!empty($preparationArray['comment'])) {
                        $preparationArray['comment'] = substr(strip_tags($preparationArray['comment']), 0, 200);
                    }
                    return $preparationArray;
                })
                ->toArray();

            // get article linked to a ReferenceArticle where type_quantite === 'article'
            $articlesPrepaByRefArticle = $articleRepository->getArticlePrepaForPickingByUser($user);

            $articlesPrepa = $this->getArticlesPrepaArrays($preparations);

            /// collecte
            $collectes = $ordreCollecteRepository->getMobileCollecte($user);

            /// On tronque le commentaire à 200 caractères (sans les tags)
            $collectes = array_map(function ($collecteArray) {
                if (!empty($collecteArray['comment'])) {
                    $collecteArray['comment'] = substr(strip_tags($collecteArray['comment']), 0, 200);
                }
                return $collecteArray;
            }, $collectes);

            $collectesIds = Stream::from($collectes)
                ->map(function ($collecteArray) {
                    return $collecteArray['id'];
                })
                ->toArray();
            $articlesCollecte = $articleRepository->getByOrdreCollectesIds($collectesIds);
            $refArticlesCollecte = $referenceArticleRepository->getByOrdreCollectesIds($collectesIds);

            /// transferOrder
            $transferOrders = $transferOrderRepository->getMobileTransferOrders($user);
            $transferOrdersIds = Stream::from($transferOrders)
                ->map(function ($transferOrder) {
                    return $transferOrder['id'];
                })
                ->toArray();
            $transferOrderArticles = array_merge(
                $articleRepository->getByTransferOrders($transferOrdersIds),
                $referenceArticleRepository->getByTransferOrders($transferOrdersIds)
            );

            // inventory
            $articlesInventory = $inventoryMissionRepository->getCurrentMissionArticlesNotTreated();
            $refArticlesInventory = $inventoryMissionRepository->getCurrentMissionRefNotTreated();

            // prises en cours
            $stockTaking = $trackingMovementRepository->getPickingByOperatorAndNotDropped($user, TrackingMovementRepository::MOUVEMENT_TRACA_STOCK);
        }

        if ($rights['demande']) {
            $handlingExpectedDateColors = [
                'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_AFTER),
                'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_D_DAY),
                'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_BEFORE)
            ];

            $handlings = $handlingRepository->getMobileHandlingsByUserTypes($user->getHandlingTypeIds());
            $removeHoursDesiredDate = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME);
            $handlings = Stream::from($handlings)
                ->map(function (array $handling) use ($handlingExpectedDateColors, $removeHoursDesiredDate) {
                    $handling['color'] = $this->expectedDateColor($handling['desiredDate'], $handlingExpectedDateColors);
                    $handling['desiredDate'] = $handling['desiredDate']
                        ? $handling['desiredDate']->format($removeHoursDesiredDate
                            ? 'd/m/Y'
                            : 'd/m/Y H:i:s')
                        : null;
                    $handling['comment'] = $handling['comment'] ? strip_tags($handling['comment']) : null;
                    return $handling;
                })->toArray();

            $handlingIds = array_map(function (array $handling) {
                return $handling['id'];
            }, $handlings);
            $handlingAttachments = array_map(
                function (array $attachment) use ($request) {
                    return [
                        'handlingId' => $attachment['handlingId'],
                        'fileName' => $attachment['originalName'],
                        'href' => $request->getSchemeAndHttpHost() . '/uploads/attachements/' . $attachment['fileName']
                    ];
                },
                $attachmentRepository->getMobileAttachmentForHandling($handlingIds)
            );

            $requestFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_HANDLING]);

            $demandeLivraisonArticles = $referenceArticleRepository->getByNeedsMobileSync();
            $demandeLivraisonTypes = array_map(function (Type $type) {
                return [
                    'id' => $type->getId(),
                    'label' => $type->getLabel(),
                ];
            }, $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]));

            $deliveryFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::DEMANDE_LIVRAISON]);
        }

        if ($rights['tracking']) {
            $trackingTaking = $trackingMovementService->getMobileUserPicking($entityManager, $user);

            $natures = array_map(
                function (Nature $nature) use ($natureService) {
                    return $natureService->serializeNature($nature);
                },
                $natureRepository->findAll()
            );
            $allowedNatureInLocations = $natureRepository->getAllowedNaturesIdByLocation();
            $trackingFreeFields = $freeFieldRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]);

            $dispatchExpectedDateColors = [
                'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
                'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
                'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
            ];

            $dispatches = $dispatchRepository->getMobileDispatches($user);
            $dispatches = Stream::from($dispatches)
                ->map(function (array $dispatch) use ($dispatchExpectedDateColors) {
                    $dispatch['color'] = $this->expectedDateColor($dispatch['endDate'] ?? null, $dispatchExpectedDateColors);
                    $dispatch['startDate'] = $dispatch['startDate'] ? $dispatch['startDate']->format('d/m/Y') : null;
                    $dispatch['endDate'] = $dispatch['endDate'] ? $dispatch['endDate']->format('d/m/Y') : null;
                    return $dispatch;
                })->toArray();
            $dispatchPacks = array_map(function($dispatchPack) {
                if(!empty($dispatchPack['comment'])) {
                    $dispatchPack['comment'] = substr(strip_tags($dispatchPack['comment']), 0, 200);
                }
                return $dispatchPack;
            }, $dispatchPackRepository->getMobilePacksFromDispatches(array_map(fn($dispatch) => $dispatch['id'], $dispatches)));
        }

        return [
            'locations' => $emplacementRepository->getLocationsArray(),
            'allowedNatureInLocations' => $allowedNatureInLocations ?? [],
            'freeFields' => Stream::from($trackingFreeFields ?? [], $requestFreeFields ?? [], $deliveryFreeFields ?? [])
                ->map(function (FreeField $freeField) {
                    return $freeField->serialize();
                })
                ->toArray(),
            'preparations' => $preparations ?? [],
            'articlesPrepa' => $articlesPrepa ?? [],
            'articlesPrepaByRefArticle' => $articlesPrepaByRefArticle ?? [],
            'livraisons' => $livraisons ?? [],
            'articlesLivraison' => array_merge(
                $articlesLivraison ?? [],
                $refArticlesLivraison ?? []
            ),
            'collectes' => $collectes ?? [],
            'articlesCollecte' => array_merge(
                $articlesCollecte ?? [],
                $refArticlesCollecte ?? []
            ),
            'transferOrders' => $transferOrders ?? [],
            'transferOrderArticles' => $transferOrderArticles ?? [],
            'handlings' => $handlings ?? [],
            'handlingAttachments' => $handlingAttachments ?? [],
            'inventoryMission' => array_merge(
                $articlesInventory ?? [],
                $refArticlesInventory ?? []
            ),
            'anomalies' => array_merge($refAnomalies ?? [], $artAnomalies ?? []),
            'trackingTaking' => $trackingTaking ?? [],
            'stockTaking' => $stockTaking ?? [],
            'demandeLivraisonTypes' => $demandeLivraisonTypes ?? [],
            'demandeLivraisonArticles' => $demandeLivraisonArticles ?? [],
            'natures' => $natures ?? [],
            'rights' => $rights,
            'translations' => $translationsRepository->findAllObjects(),
            'dispatches' => $dispatches ?? [],
            'dispatchPacks' => $dispatchPacks ?? [],
            'status' => $status
        ];
    }

    /**
     * @Rest\Post("/api/getData", name="api-get-data")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getData(Request $request,
                            UserService $userService,
                            NatureService $natureService,
                            TrackingMovementService $trackingMovementService,
                            EntityManagerInterface $entityManager)
    {
        $nomadUser = $this->getUser();

        return $this->json([
            "success" => true,
            "data" => $this->getDataArray($nomadUser, $userService, $trackingMovementService, $natureService, $request, $entityManager)
        ]);
    }

    private function apiKeyGenerator()
    {
        return md5(microtime() . rand());
    }

    /**
     * @Rest\Get("/api/nomade-versions", condition="request.isXmlHttpRequest()")
     */
    public function getAvailableVersionsAction()
    {
        return $this->json($this->getParameter('nomade_versions') ?? '*');
    }

    /**
     * @Rest\Post("/api/treatAnomalies", name= "api-treat-anomalies-inv", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function treatAnomalies(Request $request,
                                   InventoryService $inventoryService,
                                   ExceptionLoggerService $exceptionLoggerService)
    {

        $nomadUser = $this->getUser();

        $numberOfRowsInserted = 0;

        $anomalies = json_decode($request->request->get('anomalies'), true);
        $errors = [];
        $success = [];
        foreach ($anomalies as $anomaly) {
            try {
                $res = $inventoryService->doTreatAnomaly(
                    $anomaly['id'],
                    $anomaly['reference'],
                    $anomaly['is_ref'],
                    $anomaly['quantity'],
                    $anomaly['comment'],
                    $nomadUser
                );

                $success = array_merge($success, $res['treatedEntries']);

                $numberOfRowsInserted++;
            } catch (ArticleNotAvailableException|RequestNeedToBeProcessedException $exception) {
                $errors[] = $anomaly['id'];
            } catch (Throwable $throwable) {
                $exceptionLoggerService->sendLog($throwable, $request);
                throw $throwable;
            }
        }

        $s = $numberOfRowsInserted > 1 ? 's' : '';
        $data = [];
        $data['success'] = $success;
        $data['errors'] = $errors;
        $data['data']['status'] = ($numberOfRowsInserted === 0)
            ? ($anomalies > 0
                ? 'Une ou plusieus erreurs, des ordres de livraison sont en cours pour ces articles ou ils ne sont pas disponibles, veuillez recharger vos données'
                : "Aucune anomalie d'inventaire à synchroniser.")
            : ($numberOfRowsInserted . ' anomalie' . $s . ' d\'inventaire synchronisée' . $s);

        return $this->json($data);
    }

    /**
     * @Rest\Post("/api/emplacement", name="api-new-emp", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function addEmplacement(Request $request, EntityManagerInterface $entityManager): Response
    {
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if (!$emplacementRepository->findOneBy(['label' => $request->request->get('label')])) {
            $toInsert = new Emplacement();
            $toInsert
                ->setLabel($request->request->get('label'))
                ->setIsActive(true)
                ->setDescription('')
                ->setIsDeliveryPoint((bool)$request->request->get('isDelivery'));
            $entityManager->persist($toInsert);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "msg" => $toInsert->getId()
            ]);
        } else {
            throw new BadRequestHttpException("Un emplacement portant ce nom existe déjà");
        }
    }

    /**
     * @Rest\Get("/api/articles", name="api-get-articles", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getArticles(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $statutRepository = $entityManager->getRepository(Statut::class);

        $referenceActiveStatusId = $statutRepository
            ->findOneByCategorieNameAndStatutCode(ReferenceArticle::CATEGORIE, ReferenceArticle::STATUT_ACTIF)
            ->getId();

        $resData = [];

        $barCode = $request->query->get('barCode');
        $location = $request->query->get('location');

        if (!empty($barCode) && !empty($location)) {
            $statusCode = Response::HTTP_OK;

            $referenceArticleArray = $referenceArticleRepository->getOneReferenceByBarCodeAndLocation($barCode, $location);
            if (!empty($referenceArticleArray)) {
                $referenceArticle = $referenceArticleRepository->find($referenceArticleArray['id']);
                $statusReferenceArticle = $referenceArticle->getStatut();
                $statusReferenceId = $statusReferenceArticle ? $statusReferenceArticle->getId() : null;
                // we can transfer if reference is active AND it is not linked to any active orders
                $referenceArticleArray['can_transfer'] = (
                    ($statusReferenceId === $referenceActiveStatusId)
                    && !$referenceArticle->isUsedInQuantityChangingProcesses()
                );
                $resData['article'] = $referenceArticleArray;
            } else {
                $article = $articleRepository->getOneArticleByBarCodeAndLocation($barCode, $location);
                if (!empty($article)) {
                    $article['can_transfer'] = ($article['reference_status'] === ReferenceArticle::STATUT_ACTIF);
                }
                $resData['article'] = $article;
            }

            if (!empty($resData['article'])) {
                $resData['article']['is_ref'] = (int)$resData['article']['is_ref'];
            }

            $resData['success'] = !empty($resData['article']);
        } else {
            throw new BadRequestHttpException();
        }

        return new JsonResponse($resData, $statusCode);
    }

    /**
     * @Rest\Get("/api/tracking-drops", name="api-get-tracking-drops-on-location", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getTrackingDropsOnLocation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $resData = [];

        $locationLabel = $request->query->get('location');
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        $location = !empty($locationLabel)
            ? $emplacementRepository->findOneBy(['label' => $locationLabel])
            : null;

        if (!empty($locationLabel) && !isset($location)) {
            $location = $emplacementRepository->find($locationLabel);
        }

        if (!empty($location)) {
            if ($location instanceof Emplacement && $location->isOngoingVisibleOnMobile()) {
                $resData['success'] = true;
                $packMaxNumber = 50;
                $packRepository = $entityManager->getRepository(Pack::class);
                $ongoingPackIds = Stream::from($packRepository->getCurrentPackOnLocations(
                    [$location],
                    [
                        'order' => 'asc',
                        'isCount' => false,
                        'limit' => $packMaxNumber
                    ]
                ))
                    ->map(fn(array $pack) => $pack['id'])
                    ->toArray();

                $resData['trackingDrops'] = $packRepository->getPacksById($ongoingPackIds);
            } else {
                $resData['trackingDrops'] = [];
            }
        } else {
            $resData['success'] = true;
            $resData['trackingDrops'] = [];
        }

        return new JsonResponse($resData, Response::HTTP_OK);
    }

    /**
     * @Rest\Get("/api/packs", name="api_get_pack_data", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getPackData(Request $request,
                                EntityManagerInterface $entityManager,
                                NatureService $natureService): Response
    {
        $code = $request->query->get('code');
        $includeNature = $request->query->getBoolean('nature');
        $includeGroup = $request->query->getBoolean('group');
        $res = ['success' => true];

        $packRepository = $entityManager->getRepository(Pack::class);
        $pack = !empty($code)
            ? $packRepository->findOneBy(['code' => $code])
            : null;

        if ($pack) {
            $isGroup = $pack->isGroup();
            $res['isGroup'] = $isGroup;
            $res['isPack'] = !$isGroup;

            if ($includeGroup) {
                $group = $isGroup ? $pack : $pack->getParent();
                $res['group'] = $group ? $group->serialize() : null;
            }

            if ($includeNature) {
                $nature = $pack->getNature();
                $res['nature'] = !empty($nature)
                    ? $natureService->serializeNature($nature)
                    : null;
            }
        }
        else {
            $res['isGroup'] = false;
            $res['isPack'] = false;
        }

        return $this->json($res);
    }

    /**
     * @Rest\Get("/api/pack-groups", name="api_get_pack_groups", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function getPacksGroups(Request $request, EntityManagerInterface $entityManager): Response {
        $code = $request->query->get('code');

        $packRepository = $entityManager->getRepository(Pack::class);

        $pack = !empty($code)
            ? $packRepository->findOneBy(['code' => $code])
            : null;

        if ($pack) {
            if (!$pack->isGroup()) {
                $isPack = true;
                $isSubPack = $pack->getParent() !== null;
                $packSerialized = $pack->serialize();
            }
            else {
                $isPack = false;
                $packGroupSerialized = $pack->serialize();
            }
        }

        return $this->json([
            "success" => true,
            "isPack" => $isPack ?? false,
            "isSubPack" => $isSubPack ?? false,
            "pack" => $packSerialized ?? null,
            "packGroup" => $packGroupSerialized ?? null,
        ]);
    }

    /**
     * @Rest\Get("/api/group", name="api_group", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function group(Request $request,
                          EntityManagerInterface $entityManager,
                          GroupService $groupService,
                          TrackingMovementService $trackingMovementService): Response {
        $packRepository = $entityManager->getRepository(Pack::class);

        /** @var Pack $parentPack */
        $parentPack = $packRepository->findOneBy(['code' => $request->request->get("code")]);
        $isNewGroupInstance = false;
        if (!$parentPack) {
            $isNewGroupInstance = true;
            $parentPack = $groupService->createParentPack([
                'parent' => $request->request->get("code")
            ]);

            $entityManager->persist($parentPack);
        } else if ($parentPack->getChildren()->isEmpty()) {
            $isNewGroupInstance = true;
            $parentPack->incrementGroupIteration();
        }

        $packs = json_decode($request->request->get("packs"), true);

        $datetimeFromDate = function ($dateStr) {
            return DateTime::createFromFormat("d/m/Y H:i:s", $dateStr)
                ?: DateTime::createFromFormat("d/m/Y H:i", $dateStr)
                ?: null;
        };

        $dateStr = $request->request->get("date");
        $groupDate = $datetimeFromDate($dateStr);

        if ($isNewGroupInstance && !empty($packs)) {
            $groupingTrackingMovement = $trackingMovementService->createTrackingMovement(
                $parentPack,
                null,
                $this->getUser(),
                $groupDate,
                true,
                true,
                TrackingMovement::TYPE_GROUP
            );

            $entityManager->persist($groupingTrackingMovement);
        }

        foreach ($packs as $data) {
            $pack = $trackingMovementService->persistPack($entityManager, $data["code"], $data["quantity"], $data["nature_id"]);
            if (!$pack->getParent()) {
                $pack->setParent($parentPack);

                $groupingTrackingMovement = $trackingMovementService->createTrackingMovement(
                    $pack,
                    null,
                    $this->getUser(),
                    $groupDate,
                    true,
                    true,
                    TrackingMovement::TYPE_GROUP,
                    ["parent" => $parentPack]
                );

                $entityManager->persist($groupingTrackingMovement);
            }
        }

        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Groupage synchronisé",
        ]);
    }

    /**
     * @Rest\Get("/api/ungroup", name="api_ungroup", methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function ungroup(Request $request, EntityManagerInterface $manager, GroupService $groupService): Response {
        $locationRepository = $manager->getRepository(Emplacement::class);
        $packRepository = $manager->getRepository(Pack::class);

        $date = DateTime::createFromFormat("d/m/Y H:i:s", $request->request->get("date"));
        $location = $locationRepository->find($request->request->get("location"));
        $group = $packRepository->find($request->request->get("group"));

        $groupService->ungroup($manager, $group, $location, $this->getUser(), $date);
        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Dégroupage synchronisé",
        ]);
    }

    /**
     * @Rest\Get("/api/server-images", name="api_images", condition="request.isXmlHttpRequest()")
     * @Wii\RestVersionChecked()
     */
    public function getLogos(EntityManagerInterface $entityManager,
                             KernelInterface $kernel,
                             Request $request): Response
    {
        $logoKey = $request->get('key');
        if (!in_array($logoKey, [ParametrageGlobal::MOBILE_LOGO_HEADER, ParametrageGlobal::MOBILE_LOGO_LOGIN])) {
            throw new BadRequestHttpException('Unknown logo key');
        }

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $logo = $parametrageGlobalRepository->getOneParamByLabel($logoKey);

        if (!$logo) {
            return $this->json([
                "success" => false,
                'message' => 'Image non renseignée AAA'
            ]);
        }

        $projectDir = $kernel->getProjectDir();

        try {
            $imagePath = $projectDir . '/public/' . $logo;

            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $type = ($type === 'svg' ? 'svg+xml' : $type);

            $data = file_get_contents($imagePath);
            $image = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } catch (Throwable $ignored) {
            return $this->json([
                "success" => false,
                'message' => 'Image non renseignée'
            ]);
        }

        return $this->json([
            "success" => true,
            'image' => $image
        ]);
    }

    /**
     * @Rest\Post("/api/dispatches", name="api_patch_dispatches", condition="request.isXmlHttpRequest()")
     * @Wii\RestAuthenticated()
     * @Wii\RestVersionChecked()
     */
    public function patchDispatches(Request $request,
                                    DispatchService $dispatchService,
                                    EntityManagerInterface $entityManager): JsonResponse
    {
        $nomadUser = $this->getUser();

        $resData = [];

        $dispatches = json_decode($request->request->get('dispatches'), true);
        $dispatchPacksParam = json_decode($request->request->get('dispatchPacks'), true);

        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $statusRepository = $entityManager->getRepository(Statut::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $entireTreatedDispatch = [];

        $dispatchPacksByDispatch = is_array($dispatchPacksParam)
            ? array_reduce($dispatchPacksParam, function (array $acc, array $current) {
                $id = (int)$current['id'];
                $natureId = $current['natureId'];
                $quantity = $current['quantity'];
                $dispatchId = (int)$current['dispatchId'];
                if (!isset($acc[$dispatchId])) {
                    $acc[$dispatchId] = [];
                }
                $acc[$dispatchId][] = [
                    'id' => $id,
                    'natureId' => $natureId,
                    'quantity' => $quantity
                ];
                return $acc;
            }, [])
            : [];

        foreach ($dispatches as $dispatchArray) {
            /** @var Dispatch $dispatch */
            $dispatch = $dispatchRepository->find($dispatchArray['id']);
            $dispatchStatus = $dispatch->getStatut();
            if (!$dispatchStatus || !$dispatchStatus->isTreated()) {
                $treatedStatus = $statusRepository->find($dispatchArray['treatedStatusId']);
                if ($treatedStatus
                    && ($treatedStatus->isTreated() || $treatedStatus->isPartial())) {
                    $treatedPacks = [];
                    // we treat pack edits
                    if (!empty($dispatchPacksByDispatch[$dispatch->getId()])) {
                        foreach ($dispatchPacksByDispatch[$dispatch->getId()] as $packArray) {
                            $treatedPacks[] = $packArray['id'];
                            $packDispatch = $dispatchPackRepository->find($packArray['id']);
                            if (!empty($packDispatch)) {
                                if (!empty($packArray['natureId'])) {
                                    $nature = $natureRepository->find($packArray['natureId']);
                                    if ($nature) {
                                        $pack = $packDispatch->getPack();
                                        $pack->setNature($nature);
                                    }
                                }

                                $quantity = (int)$packArray['quantity'];
                                if ($quantity > 0) {
                                    $packDispatch->setQuantity($quantity);
                                }
                            }
                        }
                    }

                    $dispatchService->treatDispatchRequest($entityManager, $dispatch, $treatedStatus, $nomadUser, true, $treatedPacks);

                    if (!$treatedStatus->isPartial()) {
                        $entireTreatedDispatch[] = $dispatch->getId();
                    }
                }
            }
        }
        $statusCode = Response::HTTP_OK;
        $resData['success'] = true;
        $resData['entireTreatedDispatch'] = $entireTreatedDispatch;

        return new JsonResponse($resData, $statusCode);
    }

    private function getArticlesPrepaArrays(array $preparations, bool $isIdArray = false): array
    {
        $entityManager = $this->getDoctrine()->getManager();
        /** @var ReferenceArticleRepository $referenceArticleRepository */
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        /** @var ArticleRepository $articleRepository */
        $articleRepository = $entityManager->getRepository(Article::class);

        $preparationsIds = !$isIdArray
            ? array_map(
                function ($preparationArray) {
                    return $preparationArray['id'];
                },
                $preparations
            )
            : $preparations;
        return array_merge(
            $articleRepository->getByPreparationsIds($preparationsIds),
            $referenceArticleRepository->getByPreparationsIds($preparationsIds)
        );
    }

    private function getMenuRights($user, UserService $userService)
    {
        return [
            'demoMode' => $userService->hasRightFunction(Menu::NOMADE, Action::DEMO_MODE, $user),
            'notifications' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_NOTIFICATIONS, $user),
            'stock' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_STOCK, $user),
            'tracking' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_TRACA, $user),
            'group' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_GROUP, $user),
            'ungroup' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_UNGROUP, $user),
            'demande' => $userService->hasRightFunction(Menu::NOMADE, Action::MODULE_ACCESS_HAND, $user),
            'inventoryManager' => $userService->hasRightFunction(Menu::STOCK, Action::INVENTORY_MANAGER, $user)
        ];
    }

    private function expectedDateColor(?DateTime $date, array $colors): ?string {
        $nowStr = (new DateTime('now'))->format('Y-m-d');
        $dateStr = !empty($date) ? $date->format('Y-m-d') : null;
        $color = null;
        if ($dateStr) {
            if ($dateStr > $nowStr && isset($colors['after'])) {
                $color = $colors['after'];
            }
            if ($dateStr === $nowStr && isset($colors['DDay'])) {
                $color = $colors['DDay'];
            }
            if ($dateStr < $nowStr && isset($colors['before'])) {
                $color = $colors['before'];
            }
        }
        return $color;
    }

}
