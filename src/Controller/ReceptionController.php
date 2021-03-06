<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\InventoryCategory;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\FieldsParam;
use App\Entity\CategorieCL;
use App\Entity\MouvementStock;
use App\Entity\PurchaseRequest;
use App\Entity\PurchaseRequestLine;
use App\Entity\TrackingMovement;
use App\Entity\ParametrageGlobal;
use App\Entity\Attachment;
use App\Entity\Statut;
use App\Entity\TransferOrder;
use App\Entity\TransferRequest;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Entity\ReferenceArticle;
use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\CategoryType;

use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\DemandeLivraisonService;
use App\Service\GlobalParamService;
use App\Service\LitigeService;
use App\Service\LivraisonsManagerService;
use App\Service\MailerService;
use App\Service\MouvementStockService;
use App\Service\PreparationsManagerService;
use App\Service\TrackingMovementService;
use App\Service\PDFGeneratorService;
use App\Service\ReceptionService;
use App\Service\AttachmentService;
use App\Service\ArticleDataService;
use App\Service\RefArticleDataService;
use App\Service\TransferOrderService;
use App\Service\TransferRequestService;
use App\Service\UniqueNumberService;
use App\Service\UserService;

use App\Service\FreeFieldService;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use Exception;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/reception")
 */
class ReceptionController extends AbstractController {

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * @var ReceptionService
     */
    private $receptionService;

    /**
     * @var MouvementStockService
     */
    private $mouvementStockService;
    private $mailerService;

    public function __construct(ArticleDataService $articleDataService,
                                GlobalParamService $globalParamService,
                                UserService $userService,
                                ReceptionService $receptionService,
                                MailerService $mailerService,
                                AttachmentService $attachmentService,
                                MouvementStockService $mouvementStockService) {
        $this->mailerService = $mailerService;
        $this->attachmentService = $attachmentService;
        $this->receptionService = $receptionService;
        $this->globalParamService = $globalParamService;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
        $this->mouvementStockService = $mouvementStockService;
    }


    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function new(EntityManagerInterface $entityManager,
                        FreeFieldService $champLibreService,
                        ReceptionService $receptionService,
                        AttachmentService $attachmentService,
                        Request $request,
                        TranslatorInterface $translator): Response {

        if ($data = $request->request->all()) {
            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $reception = $receptionService->createAndPersistReception($entityManager, $currentUser, $data);

            try {
                $entityManager->flush();
            }
            /** @noinspection PhpRedundantCatchClauseInspection */
            catch (UniqueConstraintViolationException $e) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => $translator->trans('réception.Une autre réception est en cours de création, veuillez réessayer').'.'
                ]);
            }

            $champLibreService->manageFreeFields($reception, $data, $entityManager);
            $attachmentService->manageAttachments($entityManager, $reception, $request->files);
            $entityManager->flush();

            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId(),
                ])
            ];
            return new JsonResponse($data);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="reception_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(EntityManagerInterface $entityManager,
                         FreeFieldService $champLibreService,
                         ReceptionService $receptionService,
                         AttachmentService $attachmentService,
                         Request $request): Response {
        if($data = $request->request->all()) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);

            $reception = $receptionRepository->find($data['receptionId']);

            $statut = $statutRepository->find(intval($data['statut']));
            $reception->setStatut($statut);

            $fournisseur = !empty($data['fournisseur']) ? $fournisseurRepository->find($data['fournisseur']) : null;
            $reception->setFournisseur($fournisseur);

            $utilisateur = !empty($data['utilisateur']) ? $utilisateurRepository->find($data['utilisateur']) : null;
            $reception->setUtilisateur($utilisateur);

            $transporteur = !empty($data['transporteur']) ? $transporteurRepository->find($data['transporteur']) : null;
            $reception->setTransporteur($transporteur);

            $location = !empty($data['location']) ? $emplacementRepository->find($data['location']) : null;
            $reception->setLocation($location);

            $storageLocation = !empty($data['storageLocation']) ? $emplacementRepository->find($data['storageLocation']) : null;
            $reception->setStorageLocation($storageLocation);

            $emergency = !empty($data['emergency'])
                ? (
                    $data['emergency'] === "false"
                    ? null
                    : $data['emergency']
                )
                : null;
            $reception->setManualUrgent($emergency);
            $reception
                ->setOrderNumber(!empty($data['orderNumber']) ? $data['orderNumber'] : null)
                ->setDateAttendue(
                    !empty($data['dateAttendue'])
                        ? new DateTime(str_replace('/', '-', $data['dateAttendue']))
                        : null)
                ->setDateCommande(
                    !empty($data['dateCommande'])
                        ? new DateTime(str_replace('/', '-', $data['dateCommande']))
                        : null)
                ->setCommentaire(isset($data['commentaire']) ? $data['commentaire'] : null);

            $reception->removeIfNotIn($data['files'] ?? []);

            $entityManager->flush();

            $champLibreService->manageFreeFields($reception, $data, $entityManager);
            $attachmentService->manageAttachments($entityManager, $reception, $request->files);

            $entityManager->flush();
            $json = [
                'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                ]),
                'success' => true,
                'msg' => 'La réception <strong>' . $reception->getNumber() . '</strong> a bien été modifiée.'
            ];
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(EntityManagerInterface $entityManager,
                            Request $request): Response {
        if($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

            $reception = $receptionRepository->find($data['id']);

            $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

            $typeChampLibre = [];
            $champsLibresEntity = [];
            foreach($listType as $type) {
                $champsLibresComplet = $champLibreRepository->findByType($type['id']);
                $champsLibres = [];
                //création array edit pour vue
                foreach($champsLibresComplet as $champLibre) {
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'requiredEdit' => $champLibre->getRequiredEdit()
                    ];
                    $champsLibresEntity[] = $champLibre;
                }

                $typeChampLibre[] = [
                    'typeLabel' => $type['label'],
                    'typeId' => $type['id'],
                    'champsLibres' => $champsLibres,
                ];
            }

            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
            $json = $this->renderView('reception/modalEditReceptionContent.html.twig', [
                'reception' => $reception,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
                'typeChampsLibres' => $typeChampLibre,
                'fieldsParam' => $fieldsParam,
                'freeFieldsGroupedByTypes' => $champsLibresEntity
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        EntityManagerInterface $entityManager): Response {
        $purchaseRequestFilter = $request->request->get('purchaseRequestFilter');
        $data = $this->receptionService->getDataForDatatable($this->getUser(), $request->request, $purchaseRequestFilter);

        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $fieldsParam = $fieldsParamRepository->getHiddenByEntity(FieldsParam::ENTITY_CODE_RECEPTION);
        $data['columnsToHide'] = $fieldsParam;

        return new JsonResponse($data);
    }

    /**
     * @Route("/api-article/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function articleApi(EntityManagerInterface $entityManager, $id): Response {
        $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
        $receptionRepository = $entityManager->getRepository(Reception::class);

        $reception = $receptionRepository->find($id);
        $ligneArticles = $receptionReferenceArticleRepository->findByReception($reception);

        $rows = [];
        $hasBarCodeToPrint = false;
        foreach($ligneArticles as $ligneArticle) {
            $referenceArticle = $ligneArticle->getReferenceArticle();
            if(!$hasBarCodeToPrint && isset($referenceArticle)) {
                $articles = $ligneArticle->getArticles();
                $hasBarCodeToPrint = (
                    ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) ||
                    ($articles->count() > 0)
                );
            }

            $isReferenceTypeLinked = (
                isset($referenceArticle)
                && ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE)
            );

            $isArticleTypeLinked = (
                isset($referenceArticle)
                && ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE)
            );

            $rows[] = [
                "Référence" => (isset($referenceArticle) ? $referenceArticle->getReference() : ''),
                "Commande" => ($ligneArticle->getCommande() ? $ligneArticle->getCommande() : ''),
                "A recevoir" => ($ligneArticle->getQuantiteAR() ? $ligneArticle->getQuantiteAR() : 0),
                "Reçu" => ($ligneArticle->getQuantite() ? $ligneArticle->getQuantite() : 0),
                "Urgence" => ($ligneArticle->getEmergencyTriggered() ?? false),
                "Comment" => ($ligneArticle->getEmergencyComment() ?? ''),
                'Actions' => $this->renderView(
                    'reception/datatableLigneRefArticleRow.html.twig',
                    [
                        'ligneId' => $ligneArticle->getId(),
                        'ligneArticleQuantity' => $ligneArticle->getQuantite(),
                        'receptionId' => $reception->getId(),
                        'isReferenceTypeLinked' => $isReferenceTypeLinked,
                        'isArticleTypeLinked' => $isArticleTypeLinked,
                        'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                        'packFilter' => (isset($referenceArticle) ? $referenceArticle->getBarCode() : '')
                    ]
                ),
            ];
        }
        $data['data'] = $rows;
        $data['hasBarCodeToPrint'] = $hasBarCodeToPrint;
        return new JsonResponse($data);
    }

    /**
     * @Route("/liste/{purchaseRequest}", name="reception_index", methods={"GET", "POST"}, options={"expose"=true})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE})
     */
    public function index(EntityManagerInterface $entityManager,
                          PurchaseRequest $purchaseRequest = null): Response
    {
        $purchaseRequestLinesOrderNumbers = [];
        if ($purchaseRequest) {
            $purchaseRequestLinesOrderNumbers = $purchaseRequest->getPurchaseRequestLines()
                ->map(function (PurchaseRequestLine $purchaseRequestLine) {
                    return $purchaseRequestLine->getOrderNumber();
                })->toArray();
        }
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

        //TODO à modifier si plusieurs types possibles pour une réception
        $listType = $typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);
        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_RECEPTION);

        $typeChampLibre = [];
        foreach($listType as $type) {
            $champsLibres = $champLibreRepository->findByType($type['id']);
            $typeChampLibre[] = [
                'typeLabel' => $type['label'],
                'typeId' => $type['id'],
                'champsLibres' => $champsLibres,
            ];
        }

        return $this->render('reception/index.html.twig', [
            'typeChampLibres' => $typeChampLibre,
            'fieldsParam' => $fieldsParam,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::RECEPTION),
            'receptionLocation' => $this->globalParamService->getParamLocation(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION),
            'purchaseRequestFilter' => $purchaseRequest ? implode(',', $purchaseRequestLinesOrderNumbers) : 0,
            'purchaseRequest' => $purchaseRequest ? $purchaseRequest->getId() : ''
        ]);
    }

    /**
     * @Route("/supprimer", name="reception_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           RefArticleDataService $refArticleDataService,
                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);

            $reception = $receptionRepository->find($data['receptionId']);

            if ($reception) {
                $refsToUpdate = [];
                foreach ($reception->getReceptionReferenceArticles() as $receptionArticle) {
                    $reference = $receptionArticle->getReferenceArticle();
                    $refsToUpdate[] = $reference;
                    $entityManager->remove($receptionArticle);
                    $articleRepository->setNullByReception($receptionArticle);
                }

                foreach ($reception->getPurchaseRequestLines() as $line) {
                    $line->setReception(null);
                }

                foreach ($reception->getTrackingMovements() as $receptionMvtTraca) {
                    $entityManager->remove($receptionMvtTraca);
                }
                $entityManager->flush();
                foreach ($refsToUpdate as $reference) {
                    $refArticleDataService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
                }
                $entityManager->remove($reception);
                $entityManager->flush();
            }

            return $this->json([
                "redirect" => $this->generateUrl('reception_index')
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/annuler", name="reception_cancel", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function cancel(Request $request,
                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $statutPartialReception = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_PARTIELLE);
            $reception = $receptionRepository->find($data['receptionId']);
            if($reception->getStatut()->getCode() === Reception::STATUT_RECEPTION_TOTALE) {
                $reception->setStatut($statutPartialReception);
                $entityManager->flush();
            }
            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId()
                ])
            ];
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/retirer-article", name="reception_article_remove",  options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function removeArticle(EntityManagerInterface $entityManager,
                                  ReceptionService $receptionService,
                                  RefArticleDataService $refArticleDataService,
                                  Request $request): Response {
        if($data = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $purchaseRequestLineRepository = $entityManager->getRepository(PurchaseRequestLine::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            $ligneArticle = $receptionReferenceArticleRepository->find($data['ligneArticle']);
            $ligneArticleLabel = $ligneArticle->getReferenceArticle() ? $ligneArticle->getReferenceArticle()->getReference() : '';

            if(!$ligneArticle) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La référence est introuvable'
                ]);
            }

            $reception = $ligneArticle->getReception();

            $associatedMovements = $trackingMovementRepository->findBy([
                'receptionReferenceArticle' => $ligneArticle
            ]);

            $reference = $ligneArticle->getReferenceArticle();
            if($reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $newRefQuantity = $reference->getQuantiteStock() - $ligneArticle->getQuantite();
                $newRefAvailableQuantity = $newRefQuantity - $reference->getQuantiteReservee();
                if($newRefAvailableQuantity < 0) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'La suppression de la référence engendre des quantités négatives'
                    ]);
                }
                $reference->setQuantiteStock($newRefQuantity);
            }

            foreach($associatedMovements as $associatedMvt) {
                $entityManager->remove($associatedMvt);
            }

            $entityManager->remove($ligneArticle);
            $entityManager->flush();
            $refArticleDataService->setStateAccordingToRelations($reference, $purchaseRequestLineRepository, $receptionReferenceArticleRepository);
            $nbArticleNotConform = $receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statusCode = $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, $statusCode);
            $reception->setStatut($statut);

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();
            $quantity = $ligneArticle->getQuantite();
            if ($quantity) {
                $stockMovement = $this->mouvementStockService->createMouvementStock(
                    $currentUser,
                    null,
                    $quantity,
                    $reference,
                    MouvementStock::TYPE_SORTIE
                );

                $stockMovement->setReceptionOrder($reception);
                $date = new DateTime('now');
                $this->mouvementStockService->finishMouvementStock($stockMovement, $date, $reception->getLocation());
                $entityManager->persist($stockMovement);
            }

            $entityManager->flush();
            return new JsonResponse([
                'success' => true,
                'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                ]),
                'msg' => 'La référence <strong>' . $ligneArticleLabel . '</strong> a bien été supprimée.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/add-article", name="reception_article_add", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function addArticle(EntityManagerInterface $entityManager,
                               ReceptionService $receptionService,
                               Request $request): Response {
        if($contentData = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $receptionRepository = $entityManager->getRepository(Reception::class);

            $refArticleId = (int)$contentData['referenceArticle'];
            $refArticle = $referenceArticleRepository->find($refArticleId);

            $reception = $receptionRepository->find($contentData['reception']);
            $commande = $contentData['commande'];

            $receptionReferenceArticle = $reception->getReceptionReferenceArticles();

            // On vérifie que le couple (référence, commande) n'est pas déjà utilisé dans la réception
            $refAlreadyExists = $receptionReferenceArticle->filter(function(ReceptionReferenceArticle $receptionReferenceArticle) use ($refArticleId, $commande) {
                return (
                    $commande === $receptionReferenceArticle->getCommande() &&
                    $refArticleId === $receptionReferenceArticle->getReferenceArticle()->getId()
                );
            });

            if($refAlreadyExists->count() === 0) {
                $anomalie = $contentData['anomalie'];
                if($anomalie) {
                    $statutRecep = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_ANOMALIE);
                    $reception->setStatut($statutRecep);
                }

                $receptionReferenceArticle = new ReceptionReferenceArticle();
                $receptionReferenceArticle
                    ->setCommande($commande)
                    ->setAnomalie($contentData['anomalie'])
                    ->setCommentaire($contentData['commentaire'])
                    ->setReferenceArticle($refArticle)
                    ->setQuantiteAR(max($contentData['quantiteAR'], 1))// protection contre quantités négatives ou nulles
                    ->setReception($reception);

                if(array_key_exists('quantite', $contentData) && $contentData['quantite']) {
                    $receptionReferenceArticle->setQuantite(max($contentData['quantite'], 0));
                }

                $entityManager->persist($receptionReferenceArticle);
                $entityManager->flush();

                if($refArticle->getIsUrgent()) {
                    $reception->setUrgentArticles(true);
                    $receptionReferenceArticle->setEmergencyTriggered(true);
                    $receptionReferenceArticle->setEmergencyComment($refArticle->getEmergencyComment());
                }
                $status = $reception->getStatut() ? $reception->getStatut()->getCode() : null;

                if ($status === Reception::STATUT_EN_ATTENTE || $status === Reception::STATUT_RECEPTION_PARTIELLE) {
                    $refArticle->setOrderState(ReferenceArticle::WAIT_FOR_RECEPTION_ORDER_STATE);
                }
                $entityManager->flush();

                $json = [
                    'success' => true,
                    'msg' => 'La référence <strong>' . $refArticle->getReference() . '</strong> a bien été ajoutée.',
                    'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                        'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                        'reception' => $reception,
                        'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                    ])
                ];
            } else {
                $json = [
                    'success' => false,
                    'msg' => 'Attention ! La référence et le numéro de commande d\'achat saisis existent déjà pour cette réception.'
                ];
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier-article", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEditArticle(EntityManagerInterface $entityManager,
                                   Request $request): Response {
        if($data = json_decode($request->getContent(), true)) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $ligneArticle = $receptionReferenceArticleRepository->find($data['id']);
            $canUpdateQuantity = $ligneArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE;

            $json = $this->renderView(
                'reception/modalEditLigneArticleContent.html.twig',
                [
                    'ligneArticle' => $ligneArticle,
                    'canUpdateQuantity' => $canUpdateQuantity,
                    'minValue' => $ligneArticle->getQuantite() ?? 0
                ]
            );
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-article", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editArticle(EntityManagerInterface $entityManager,
                                ReceptionService $receptionService,
                                Request $request,
                                TrackingMovementService $trackingMovementService): Response {
        if($data = json_decode($request->getContent(), true)) {
            $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
            $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $receptionReferenceArticle = $receptionReferenceArticleRepository->find($data['article']);
            $reception = $receptionReferenceArticle->getReception();
            $quantite = $data['quantite'];
            $receivedQuantity = $receptionReferenceArticle->getQuantite();

            if(empty($receivedQuantity)) {
                $refArticle = $referenceArticleRepository->find($data['referenceArticle']);
                $receptionReferenceArticle->setReferenceArticle($refArticle);
            }

            $receptionReferenceArticle
                ->setCommande($data['commande'])
                ->setAnomalie($data['anomalie'])
                ->setQuantiteAR(max($data['quantiteAR'], 0))// protection contre quantités négatives
                ->setCommentaire($data['commentaire']);

            $typeQuantite = $receptionReferenceArticle->getReferenceArticle()->getTypeQuantite();
            if($typeQuantite === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
                $oldReceivedQuantity = $receptionReferenceArticle->getQuantite();
                $newReceivedQuantity = max((int)$quantite, 0);
                $diffReceivedQuantity = $newReceivedQuantity - $oldReceivedQuantity;
                // protection quantité reçue <= quantité à recevoir
                if($receptionReferenceArticle->getQuantiteAR() && $quantite > $receptionReferenceArticle->getQuantiteAR()) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'La quantité reçue ne peut pas être supérieure à la quantité à recevoir.'
                    ]);
                }

                /** @var Utilisateur $currentUser */
                $currentUser = $this->getUser();
                $receptionLocation = $reception->getLocation();
                $now = new DateTime('now');

                if($diffReceivedQuantity != 0) {
                    $newRefQuantity = $referenceArticle->getQuantiteStock() + $diffReceivedQuantity;
                    if($newRefQuantity - $referenceArticle->getQuantiteReservee() < 0) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' =>
                                'Vous ne pouvez pas avoir reçu '
                                . $newReceivedQuantity
                                . ' : la quantité disponible de la référence est : '
                                . $referenceArticle->getQuantiteDisponible()
                        ]);
                    } else {
                        $mouvementStock = $this->mouvementStockService->createMouvementStock(
                            $currentUser,
                            null,
                            abs($diffReceivedQuantity),
                            $referenceArticle,
                            $diffReceivedQuantity < 0 ? MouvementStock::TYPE_SORTIE : MouvementStock::TYPE_ENTREE
                        );
                        $mouvementStock->setReceptionOrder($reception);

                        $this->mouvementStockService->finishMouvementStock(
                            $mouvementStock,
                            $now,
                            $receptionLocation
                        );
                        $entityManager->persist($mouvementStock);
                        $createdMvt = $trackingMovementService->createTrackingMovement(
                            $referenceArticle->getBarCode(),
                            $receptionLocation,
                            $currentUser,
                            $now,
                            false,
                            true,
                            TrackingMovement::TYPE_DEPOSE,
                            [
                                'mouvementStock' => $mouvementStock,
                                'quantity' => $mouvementStock->getQuantity(),
                                'from' => $reception,
                                'receptionReferenceArticle' => $receptionReferenceArticle
                            ]
                        );

                        $receptionReferenceArticle->setQuantite($newReceivedQuantity); // protection contre quantités négatives
                        $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                        $referenceArticle->setQuantiteStock($newRefQuantity);
                        $entityManager->persist($createdMvt);
                    }
                }
            }

            if(array_key_exists('articleFournisseur', $data) && $data['articleFournisseur']) {
                $articleFournisseur = $articleFournisseurRepository->find($data['articleFournisseur']);
                $receptionReferenceArticle->setArticleFournisseur($articleFournisseur);
            }

            $entityManager->flush();

            $referenceLabel = $receptionReferenceArticle->getReferenceArticle() ? $receptionReferenceArticle->getReferenceArticle()->getReference() : '';

            return new JsonResponse([
                'success' => true,
                'msg' => 'La référence <strong>' . $referenceLabel . '</strong> a bien été modifiée.',
                'entete' => $this->renderView('reception/reception-show-header.html.twig', [
                    'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
                    'reception' => $reception,
                    'showDetails' => $receptionService->createHeaderDetailsConfig($reception)
                ])
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="reception_show", methods={"GET", "POST"})
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE})
     */
    public function show(EntityManagerInterface $entityManager,
                         GlobalParamService $globalParamService,
                         ReceptionService $receptionService,
                         Reception $reception): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $listTypesDL = $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]);
        $typeChampLibreDL = [];

        foreach($listTypesDL as $typeDL) {
            $champsLibresDL = $champLibreRepository->findByTypeAndCategorieCLLabel($typeDL, CategorieCL::DEMANDE_LIVRAISON);

            $typeChampLibreDL[] = [
                'typeLabel' => $typeDL->getLabel(),
                'typeId' => $typeDL->getId(),
                'champsLibres' => $champsLibresDL,
            ];
        }

        $createDL = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::CREATE_DL_AFTER_RECEPTION]);
        $needsCurrentUser = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEMANDEUR_DANS_DL);

        $defaultDisputeStatus = $statutRepository->getIdDefaultsByCategoryName(CategorieStatut::LITIGE_RECEPT);
        return $this->render("reception/show.html.twig", [
            'reception' => $reception,
            'modifiable' => $reception->getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE,
            'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, 'displayOrder'),
            'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
            'utilisateurs' => $utilisateurRepository->getIdAndLibelleBySearch(''),
            'typeChampsLibres' => $typeChampLibreDL,
            'createDL' => $createDL ? $createDL->getValue() : false,
            'defaultDeliveryLocations' => $globalParamService->getDefaultDeliveryLocationsByTypeId($entityManager),
            'defaultDisputeStatusId' => $defaultDisputeStatus[0] ?? null,
            'needsCurrentUser' => $needsCurrentUser,
            'detailsHeader' => $receptionService->createHeaderDetailsConfig($reception)
        ]);
    }

    /**
     * @Route(
     *     "/autocomplete-art{reception}",
     *     name="get_article_reception",
     *     options={"expose"=true},
     *     methods="GET|POST",
     *     condition="request.isXmlHttpRequest()"
     * )
     *
     * @param ArticleDataService $articleDataService
     * @param Reception $reception
     * @return JsonResponse
     */
    public function getArticles(ArticleDataService $articleDataService,
                                Reception $reception): JsonResponse {
        $articles = [];
        foreach($reception->getReceptionReferenceArticles() as $rra) {
            foreach($rra->getArticles() as $article) {
                if($articleDataService->articleCanBeAddedInDispute($article)) {
                    $articles[] = [
                        'id' => $article->getId(),
                        'text' => $article->getBarCode(),
                        'numReception' => $article->getReceptionReferenceArticle()];
                }
            }
        }

        return new JsonResponse([
            'results' => $articles
        ]);
    }

    /**
     * @Route("/autocomplete-ref-art/{reception}", name="get_ref_article_reception", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     *
     * @param Request $request
     * @param Reception $reception
     *
     * @param EntityManagerInterface $entityManager
     * @param RefArticleDataService $refArticleDataService
     * @return JsonResponse
     */
    public function getRefTypeQtyArticle(Request $request,
                                         Reception $reception,
                                         EntityManagerInterface $entityManager,
                                         RefArticleDataService $refArticleDataService) {
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $articlesFournisseurArrays = [];

        $selectedReference = $request->query->get('reference');
        $selectedCommande = $request->query->get('commande');

        $ref = array_map(
            function($item) use ($articleFournisseurRepository, &$articlesFournisseurArrays, $refArticleDataService) {
                if(!isset($articlesFournisseurArrays[$item['reference']])) {
                    $articlesFournisseurArrays[$item['reference']] = $articleFournisseurRepository->getIdAndLibelleByRefRef($item['reference']);
                }
                return [
                    'id' => "{$item['reference']}_{$item['commande']}",
                    'reference' => $item['reference'],
                    'commande' => $item['commande'],
                    'defaultArticleFournisseur' => count($articlesFournisseurArrays[$item['reference']]) === 1
                        ? [
                            'text' => $articlesFournisseurArrays[$item['reference']][0]['reference'],
                            'value' => $articlesFournisseurArrays[$item['reference']][0]['id']
                        ]
                        : null,
                    'text' => "{$item['reference']} – {$item['commande']}"
                ];
            },
            $referenceArticleRepository->getRefTypeQtyArticleByReception($reception->getId(), $selectedReference, $selectedCommande)
        );

        return new JsonResponse(['results' => $ref]);
    }

    /**
     * @Route("/ligne-article-conditionnement", name="get_ligne_article_conditionnement", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     */
    public function getLigneArticleCondtionnement(Request $request,
                                                  EntityManagerInterface $entityManager) {

        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $reference = $request->query->get('reference');
        $commande = $request->query->get('commande');
        $quantity = $request->query->get('quantity');
        $defaultArticleFournisseurReference = $request->query->get('defaultArticleFournisseurReference');

        // TODO verif null

        /** @var ReferenceArticle $refArticle */
        $refArticle = $referenceArticleRepository->findOneByReference($reference);

        $typeArticle = $refArticle->getType();

        $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($typeArticle, CategorieCL::ARTICLE);
        $response = new Response();
        $response->setContent($this->renderView(
            'reception/conditionnementArticleTemplate.html.twig',
            [
                'reception' => [
                    'refArticleId' => $refArticle->getId(),
                    'reference' => $reference,
                    'referenceLabel' => $refArticle->getLibelle(),
                    'commande' => $commande,
                    'quantity' => $quantity,
                    'defaultArticleFournisseurReference' => $defaultArticleFournisseurReference,
                ],
                'typeArticle' => $typeArticle ? $typeArticle->getLabel() : '',
                'champsLibres' => $champsLibres,
                'references' => $articleFournisseurRepository->getIdAndLibelleByRef($refArticle)
            ]
        ));
        return $response;
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_reception",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editLitige(EntityManagerInterface $entityManager,
                               ArticleDataService $articleDataService,
                               LitigeService $litigeService,
                               Request $request): Response {
        $post = $request->request;

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $litigeRepository = $entityManager->getRepository(Litige::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $litige = $litigeRepository->find($post->get('id'));
        $typeBefore = $litige->getType()->getId();
        $typeBeforeName = $litige->getType()->getLabel();
        $typeAfter = (int)$post->get('typeLitige');
        $statutBeforeId = $litige->getStatus()->getId();
        $statutBeforeName = $litige->getStatus()->getNom();
        $statutAfterId = (int)$post->get('statutLitige');
        $statutAfter = $statutRepository->find($statutAfterId);

        $articlesNotAvailableCounter = $litige
            ->getArticles()
            ->filter(function(Article $article) {
                // articles non disponibles
                return in_array(
                    $article->getStatut()->getNom(),
                    [
                        Article::STATUT_EN_TRANSIT,
                        Article::STATUT_INACTIF
                    ]
                );
            })
            ->count();

        if(!$statutAfter->isTreated()
            && $articlesNotAvailableCounter > 0) {
            return new JsonResponse([
                'success' => false,
                'msg' => 'Vous ne pouvez pas passer le litige dans un statut non traité car il concerne des articles non disponibles.'
            ]);
        }

        $litige
            ->setDeclarant($utilisateurRepository->find($post->get('declarantLitige')))
            ->setUpdateDate(new DateTime('now'))
            ->setType($typeRepository->find($post->get('typeLitige')))
            ->setStatus($statutAfter);

        $errorResponse = $this->addArticleIntoDispute($entityManager, $articleDataService, $post->get('colis'), $litige);
        if($errorResponse) {
            return $errorResponse;
        }

        if($post->get('emergency')) {
            $litige->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if(!empty($buyers = $post->get('acheteursLitige'))) {
            // on détache les colis existants...
            $existingBuyers = $litige->getBuyers();
            foreach($existingBuyers as $buyer) {
                $litige->removeBuyer($buyer);
            }
            // ... et on ajoute ceux sélectionnés
            $listBuyer = explode(',', $buyers);
            foreach($listBuyer as $buyerId) {
                $litige->addBuyer($utilisateurRepository->find($buyerId));
            }
        }
        $entityManager->flush();

        $comment = '';
        $statutinstance = $statutRepository->find($post->get('statutLitige'));
        $commentStatut = $statutinstance->getComment();
        if($typeBefore !== $typeAfter) {
            $comment .= "Changement du type : " . $typeBeforeName . " -> " . $litige->getType()->getLabel() . ".";
        }
        if($statutBeforeId !== $statutAfterId) {
            if(!empty($comment)) {
                $comment .= "\n";
            }
            $comment .= "Changement du statut : " .
                $statutBeforeName . " -> " . $litige->getStatus()->getNom() . "." .
                (!empty($commentStatut) ? ("\n" . $commentStatut . ".") : '');
        }
        if($post->get('commentaire')) {
            if(!empty($comment)) {
                $comment .= "\n";
            }
            $comment .= trim($post->get('commentaire'));
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if(!empty($comment)) {
            $histoLitige = new LitigeHistoric();
            $histoLitige
                ->setLitige($litige)
                ->setDate(new DateTime('now'))
                ->setUser($currentUser)
                ->setComment($comment);
            $entityManager->persist($histoLitige);
            $entityManager->flush();
        }

        $listAttachmentIdToKeep = $post->get('files') ?? [];
        $attachments = $litige->getAttachments()->toArray();
        foreach($attachments as $attachment) {
            /** @var Attachment $attachment */
            if(!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                $this->attachmentService->removeAndDeleteAttachment($attachment, $litige);
            }
        }

        $this->createAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
        $entityManager->flush();
        $isStatutChange = ($statutBeforeId !== $statutAfterId);
        if($isStatutChange) {
            $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_RECEPTION, true);
        }
        return new JsonResponse([
            'success' => true,
            'msg' => 'Le litige <strong>' . $litige->getNumeroLitige() . '</strong> a bien été modifié.'
        ]);
    }

    /**
     * @Route("/creer-litige", name="litige_new_reception", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::CREATE}, mode=HasPermission::IN_JSON)
     */
    public function newLitige(EntityManagerInterface $entityManager,
                              LitigeService $litigeService,
                              ArticleDataService $articleDataService,
                              Request $request,
                              UniqueNumberService $uniqueNumberService,
                              TranslatorInterface $translator): Response
    {
        $post = $request->request;

        $typeRepository = $entityManager->getRepository(Type::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

        $litige = new Litige();

        $now = new DateTime('now');

        $disputeNumber = $uniqueNumberService->createUniqueNumber($entityManager, Litige::DISPUTE_RECEPTION_PREFIX, Litige::class, UniqueNumberService::DATE_COUNTER_FORMAT_DEFAULT);

        $litige
            ->setStatus($statutRepository->find($post->get('statutLitige')))
            ->setType($typeRepository->find($post->get('typeLitige')))
            ->setDeclarant($utilisateurRepository->find($post->get('declarantLitige')))
            ->setCreationDate($now)
            ->setNumeroLitige($disputeNumber);

        $errorResponse = $this->addArticleIntoDispute($entityManager, $articleDataService, $post->get('colisLitige'), $litige);
        if($errorResponse) {
            return $errorResponse;
        }

        if($post->get('emergency')) {
            $litige->setEmergencyTriggered($post->get('emergency') === 'true');
        }
        if(!empty($buyers = $post->get('acheteursLitige'))) {
            $listBuyers = explode(',', $buyers);
            foreach($listBuyers as $buyer) {
                $litige->addBuyer($utilisateurRepository->find($buyer));
            }
        }
        $statutinstance = $statutRepository->find($post->get('statutLitige'));
        $commentStatut = $statutinstance->getComment();

        $trimCommentStatut = trim($commentStatut);
        $userComment = trim($post->get('commentaire'));
        $nl = !empty($userComment) ? "\n" : '';
        $commentaire = $userComment . (!empty($trimCommentStatut) ? ($nl . $commentStatut) : '');

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        if(!empty($commentaire)) {
            $histo = new LitigeHistoric();
            $histo
                ->setDate(new DateTime('now'))
                ->setComment($commentaire)
                ->setLitige($litige)
                ->setUser($currentUser);
            $entityManager->persist($histo);
        }

        $entityManager->persist($litige);

        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'success' => false,
                'msg' => $translator->trans('réception.Un autre litige de réception est en cours de création, veuillez réessayer').'.'
            ]);
        }

        $this->createAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
        $entityManager->flush();
        $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_RECEPTION);

        return new JsonResponse([
            'success' => true,
            'msg' => 'Le litige <strong>' . $litige->getNumeroLitige() . '</strong> a bien été créé.'
        ]);
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit_reception", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiEditLitige(EntityManagerInterface $entityManager,
                                  Request $request): Response {
        if($data = json_decode($request->getContent(), true)) {
            $typeRepository = $entityManager->getRepository(Type::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($data['litigeId']);
            $colisCode = [];
            $acheteursCode = [];

            foreach($litige->getArticles() as $colis) {
                $colisCode[] = [
                    'id' => $colis->getId(),
                    'text' => $colis->getBarCode()
                ];
            }
            foreach($litige->getBuyers() as $buyer) {
                $acheteursCode[] = $buyer->getId();
            }

            $html = $this->renderView('reception/modalEditLitigeContent.html.twig', [
                'litige' => $litige,
                'typesLitige' => $typeRepository->findByCategoryLabels([CategoryType::LITIGE]),
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT, 'displayOrder'),
                'attachments' => $attachmentRepository->findBy(['litige' => $litige]),
                'utilisateurs' => $utilisateurRepository->getIdAndLibelleBySearch(''),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode, 'acheteurs' => $acheteursCode]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete_reception", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::QUALI, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $statutRepository = $entityManager->getRepository(Statut::class);

            $dispute = $litigeRepository->find($data['litige']);
            $disputeNumber = $dispute->getNumeroLitige();
            $articlesInDispute = $dispute->getArticles()->toArray();

            $articleStatusAvailable = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            /** @var Article $article */
            foreach($articlesInDispute as $article) {
                $article->removeLitige($dispute);
                $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
            }

            $entityManager->remove($dispute);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le litige <strong>' . $disputeNumber . '</strong> a bien été supprimé.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/litiges/api/{reception}", name="litige_reception_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function apiReceptionLitiges(EntityManagerInterface $entityManager,
                                        Reception $reception): Response {

        $litigeRepository = $entityManager->getRepository(Litige::class);

        /** @var Litige[] $litiges */
        $litiges = $litigeRepository->findByReception($reception);

        $rows = [];

        foreach($litiges as $litige) {
            $buyers = [];
            $articles = [];
            foreach($litige->getBuyers() as $buyer) {
                $buyers[] = $buyer->getUsername();
            }
            foreach($litige->getArticles() as $article) {
                $articles[] = $article->getBarCode();
            }
            $lastHistoric = count($litige->getLitigeHistorics()) > 0
                ?
                $litige->getLitigeHistorics()[count($litige->getLitigeHistorics()) - 1]->getComment()
                :
                '';
            $rows[] = [
                'type' => $litige->getType()->getLabel(),
                'status' => $litige->getStatus()->getNom(),
                'lastHistoric' => $lastHistoric,
                'date' => $litige->getCreationDate()->format('d/m/Y H:i'),
                'actions' => $this->renderView('reception/datatableLitigesRow.html.twig', [
                    'receptionId' => $reception->getId(),
                    'url' => [
                        'edit' => $this->generateUrl('litige_edit_reception', ['id' => $litige->getId()])
                    ],
                    'litigeId' => $litige->getId(),
                    'disputeNumber' => $litige->getNumeroLitige()
                ]),
                'urgence' => $litige->getEmergencyTriggered()
            ];
        }

        $data['data'] = $rows;

        return new JsonResponse($data);
    }

    /**
     * @Route("/finir", name="reception_finish", methods={"GET", "POST"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function finish(Request $request,
                           EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {

            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);

            $reception = $receptionRepository->find($data['id']);
            $listReceptionReferenceArticle = $receptionReferenceArticleRepository->findByReception($reception);

            if(empty($listReceptionReferenceArticle)) {
                return new JsonResponse('Vous ne pouvez pas finir une réception sans article.');
            } else {
                if($data['confirmed'] === true) {
                    $this->validateReception($entityManager, $reception);
                    return new JsonResponse(1);
                } else {
                    $partielle = false;
                    foreach($listReceptionReferenceArticle as $receptionRA) {
                        if($receptionRA->getQuantite() !== $receptionRA->getQuantiteAR()) $partielle = true;
                    }
                    if(!$partielle) {
                        $this->validateReception($entityManager, $reception);
                    }
                    return new JsonResponse($partielle ? 0 : 1);
                }
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Reception $reception
     * @throws Exception
     */
    private function validateReception(EntityManagerInterface $entityManager,
                                       $reception) {
        $statutRepository = $entityManager->getRepository(Statut::class);

        $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::RECEPTION, Reception::STATUT_RECEPTION_TOTALE);
        $now = new DateTime('now');

        $reception
            ->setStatut($statut)
            ->setDateFinReception($now)
            ->setDateCommande($now);

        $entityManager->flush();
    }

    /**
     * @Route("/obtenir-modal-for-ref", name="get_modal_new_ref", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::CREATE_REF_FROM_RECEP}, mode=HasPermission::IN_JSON)
     */
    public function checkIfQuantityArticle(): Response {
        $entityManager = $this->getDoctrine()->getManager();

        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);
        $inventoryCategoryRepository = $entityManager->getRepository(InventoryCategory::class);

        $types = $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]);

        $inventoryCategories = $inventoryCategoryRepository->findAll();
        $typeChampLibre = [];
        foreach($types as $type) {
            $champsLibres = $champLibreRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::REFERENCE_ARTICLE);
            $typeChampLibre[] = [
                'typeLabel' => $type->getLabel(),
                'typeId' => $type->getId(),
                'champsLibres' => $champsLibres,
            ];
        }
        return new JsonResponse($this->renderView('reception/modalNewRefArticle.html.twig', [
            'typeChampsLibres' => $typeChampLibre,
            'types' => $typeRepository->findByCategoryLabels([CategoryType::ARTICLE]),
            'categories' => $inventoryCategories,
            "stockManagement" => [
                ReferenceArticle::STOCK_MANAGEMENT_FEFO,
                ReferenceArticle::STOCK_MANAGEMENT_FIFO
            ],
        ]));
    }

    /**
     * @Route("/verif-avant-suppression", name="ligne_recep_check_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     */
    public function checkBeforeLigneDelete(EntityManagerInterface $entityManager,
                                           Request $request) {
        if($id = json_decode($request->getContent(), true)) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $nbArticles = $receptionReferenceArticleRepository->countArticlesByRRA($id);
            $ligneArticle = $receptionReferenceArticleRepository->find($id);
            $reference = $ligneArticle->getReferenceArticle();
            $newRefQuantity = $reference->getQuantiteStock() - $ligneArticle->getQuantite();
            $newRefAvailableQuantity = $newRefQuantity - $reference->getQuantiteReservee();
            if(intval($nbArticles) === 0 && ($newRefAvailableQuantity >= 0 || $reference->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE)) {
                $delete = true;
                $html = $this->renderView('reception/modalDeleteLigneArticleRight.html.twig');
            } else {
                $delete = false;
                if(intval($nbArticles) > 0) {
                    $html = $this->renderView('reception/modalDeleteLigneArticleWrong.html.twig');
                } else {
                    $html = $this->renderView('reception/modalDeleteLigneArticleWrong.html.twig', [
                        'msg' => 'En effet, cela décrémenterait le stock de '
                            . $ligneArticle->getQuantite() . ' alors que la quantité disponible de la référence est de '
                            . $reference->getQuantiteDisponible() . '.'
                    ]);
                }
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
    }


    /**
     * @Route("/{reception}/etiquettes", name="reception_bar_codes_print", options={"expose"=true})
     * @param Reception $reception
     * @param EntityManagerInterface $entityManager
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getReceptionBarCodes(Reception $reception,
                                         EntityManagerInterface $entityManager,
                                         RefArticleDataService $refArticleDataService,
                                         ArticleDataService $articleDataService,
                                         PDFGeneratorService $PDFGeneratorService): Response {
        $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
        $listReceptionReferenceArticle = $receptionReferenceArticleRepository->findByReception($reception);
        $barcodeConfigs = array_reduce(
            $listReceptionReferenceArticle,
            function(array $carry, ReceptionReferenceArticle $recepRef) use ($refArticleDataService, $articleDataService, $reception): array {
                $referenceArticle = $recepRef->getReferenceArticle();

                if($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    $carry[] = $refArticleDataService->getBarcodeConfig($referenceArticle);
                } else {
                    $articlesReception = $recepRef->getArticles()->toArray();
                    if(!empty($articlesReception)) {
                        array_push(
                            $carry,
                            ...array_map(
                                function(Article $article) use ($articleDataService, $reception) {
                                    return $articleDataService->getBarcodeConfig($article, $reception);
                                },
                                $articlesReception
                            )
                        );
                    }
                }
                return $carry;
            },
            []);

        if(!empty($barcodeConfigs)) {
            $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'articles_reception');
            $pdf = $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs);
            return new PdfResponse($pdf, $fileName);
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }


    /**
     * @Route("/{reception}/ligne-article/{ligneArticle}/etiquette", name="reception_ligne_article_bar_code_print", options={"expose"=true})
     * @param Reception $reception
     * @param ReceptionReferenceArticle $ligneArticle
     * @param RefArticleDataService $refArticleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getReceptionLigneArticleBarCode(Reception $reception,
                                                    ReceptionReferenceArticle $ligneArticle,
                                                    RefArticleDataService $refArticleDataService,
                                                    PDFGeneratorService $PDFGeneratorService): Response {
        if($reception->getReceptionReferenceArticles()->contains($ligneArticle) && $ligneArticle->getReferenceArticle()) {
            $barcodeConfigs = [$refArticleDataService->getBarcodeConfig($ligneArticle->getReferenceArticle())];
            $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'articles_reception');
            $pdf = $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs);
            return new PdfResponse($pdf, $fileName);
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/apiArticle", name="article_by_reception_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function apiArticle(EntityManagerInterface $entityManager,
                               Request $request): Response {
        if($ligne = $request->request->get('ligne')) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $ligne = $receptionReferenceArticleRepository->find(intval($ligne));
            $data = $this->articleDataService->getDataForDatatableByReceptionLigne($ligne, $this->getUser());

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="reception_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::ORDRE, Action::DISPLAY_RECE}, mode=HasPermission::IN_JSON)
     */
    public function checkReceptionCanBeDeleted(EntityManagerInterface $entityManager,
                                               Request $request): Response {
        if($receptionId = json_decode($request->getContent(), true)) {
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            if($receptionReferenceArticleRepository->countByReceptionId($receptionId) == 0) {
                $delete = true;
                $html = $this->renderView('reception/modalDeleteReceptionRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('reception/modalDeleteReceptionWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_receptions_csv", options={"expose"=true}, methods={"GET"})
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param CSVExportService $CSVExportService
     * @param Request $request
     * @return Response
     */
    public function getReceptionCSV(EntityManagerInterface $entityManager,
                                    TranslatorInterface $translator,
                                    CSVExportService $CSVExportService,
                                    Request $request): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch(Throwable $throwable) {
        }

        if(isset($dateTimeMin) && isset($dateTimeMax)) {
            $receptionRepository = $entityManager->getRepository(Reception::class);
            $receptions = $receptionRepository->getByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = [
                $translator->trans('réception.n° de réception'),
                'n° de commande',
                'fournisseur',
                'utilisateur',
                'statut',
                'date de création',
                'date de fin',
                'commentaire',
                'quantité à recevoir',
                'quantité reçue',
                'emplacement de stockage',
                'réception urgente',
                'référence urgente',
                'destinataire',
                'référence',
                'libellé',
                'quantité stock',
                'type',
                'code-barre reference',
                'code-barre article',
            ];
            $nowStr = date("d-m-Y_H:i");
            $addedRefs = [];

            return $CSVExportService->createBinaryResponseFromData(
                "export-" . str_replace(["/", "\\"], "-", $translator->trans('réception.réception')) . "-" . $nowStr  . ".csv",
                $receptions,
                $csvHeader,
                function($reception) use (&$addedRefs) {
                    $rows = [];
                    if($reception['articleId'] || $reception['referenceArticleId']) {
                        if($reception['articleId']) {
                            $row = $this->serializeReception($reception);

                            $row[] = $reception['requesterUsername'] ?: '';
                            $row[] = $reception['articleReference'] ?: '';
                            $row[] = $reception['articleLabel'] ?: '';
                            $row[] = $reception['articleQuantity'] ?: '';
                            $row[] = $reception['articleTypeLabel'] ?: '';
                            $row[] = $reception['articleReferenceArticleBarcode'] ?: '';
                            $row[] = $reception['articleBarcode'] ?: '';

                            $rows[] = $row;
                        }

                        else {
                            if (!isset($addedRefs[$reception['referenceArticleId']])) {
                                $addedRefs[$reception['referenceArticleId']] = true;
                                $row = $this->serializeReception($reception);

                                $row[] = '';
                                $row[] = $reception['referenceArticleReference'] ?: '';
                                $row[] = $reception['referenceArticleLibelle'] ?: '';
                                $row[] = $reception['referenceArticleQuantiteStock'] ?: '';
                                $row[] = $reception['referenceArticleTypeLabel'] ?: '';
                                $row[] = $reception['referenceArticleBarcode'] ?: '';

                                $rows[] = $row;
                            }
                        }
                    } else {
                        $rows[] = $this->serializeReception($reception);
                    }
                    return $rows;
                }
            );

        } else {
            throw new BadRequestHttpException();
        }
    }

    private function serializeReception(array $reception): array {
        return [
            $reception['number'] ?: '',
            $reception['orderNumber'] ?: '',
            $reception['providerName'] ?: '',
            $reception['userUsername'] ?: '',
            $reception['statusName'] ?: '',
            FormatHelper::datetime($reception['date']),
            FormatHelper::datetime($reception['dateFinReception']),
            $reception['commentaire'] ? strip_tags($reception['commentaire']) : '',
            $reception['receptionRefArticleQuantiteAR'] ?: '',
            (!$reception['referenceArticleId'] && !$reception['articleId']
                ? ''
                : ($reception['receptionRefArticleQuantite']
                    ?: 0)),
            $reception['storageLocation'] ?: '',
            $reception['receptionEmergency'] ? 'oui' : 'non',
            $reception['referenceEmergency'] ? 'oui' : 'non'
        ];
    }

    /**
     * @Route("/avec-conditionnement/{reception}", name="reception_new_with_packing", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function newWithPacking(Request $request,
                                   TransferRequestService $transferRequestService,
                                   TransferOrderService $transferOrderService,
                                   DemandeLivraisonService $demandeLivraisonService,
                                   TranslatorInterface $translator,
                                   EntityManagerInterface $entityManager,
                                   Reception $reception,
                                   FreeFieldService $champLibreService,
                                   TrackingMovementService $trackingMovementService,
                                   MouvementStockService $mouvementStockService,
                                   PreparationsManagerService $preparationsManagerService,
                                   LivraisonsManagerService $livraisonsManagerService): Response {

        $now = new DateTime('now');
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        if($data = json_decode($request->getContent(), true)) {
            $articles = $data['conditionnement'];
            $receptionReferenceArticleRepository = $entityManager->getRepository(ReceptionReferenceArticle::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $paramGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $totalQuantities = [];
            foreach($articles as $article) {
                $rra = $receptionReferenceArticleRepository->findOneByReceptionAndCommandeAndRefArticleId(
                    $reception,
                    $article['noCommande'],
                    $article['refArticle']
                );
                if(!isset($totalQuantities[$rra->getId()])) {
                    $totalQuantities[$rra->getId()] = ($rra->getQuantite() ?? 0);
                }
                $totalQuantities[$rra->getId()] += $article['quantite'];
            }

            foreach($totalQuantities as $rraId => $totalQuantity) {
                $rra = $receptionReferenceArticleRepository->find($rraId);

                // protection quantité reçue <= quantité à recevoir
                if($totalQuantity > $rra->getQuantiteAR() || $totalQuantity < 0) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Erreur, la quantité reçue doit être inférieure ou égale à la quantité à recevoir.'
                    ]);
                }
                $rra->setQuantite($totalQuantity);
            }

            // optionnel : crée la demande de livraison
            $needCreateLivraison = (bool)$data['create-demande'];
            $needCreateTransfer = (bool)$data['create-demande-transfert'];
            if ($needCreateLivraison && $needCreateTransfer) {
                return $this->json([
                    'success' => false,
                    'msg' => 'Vous ne pouvez pas créer une demande de livraison et une demande de transfert en même temps.'
                ]);
            }
            $request = null;
            $demande = null;
            $createDirectDelivery = (bool)$data['direct-delivery'];

            if($needCreateLivraison) {
                // optionnel : crée l'ordre de prépa
                $paramCreatePrepa = $paramGlobalRepository->findOneBy(['label' => ParametrageGlobal::CREATE_PREPA_AFTER_DL]);
                $needCreatePrepa = $paramCreatePrepa ? $paramCreatePrepa->getValue() : false;
                $data['needPrepa'] = $needCreatePrepa && !$createDirectDelivery;

                $demande = $demandeLivraisonService->newDemande($data, $entityManager, $champLibreService);
                if ($demande instanceof Demande) {
                    $entityManager->persist($demande);

                    if ($createDirectDelivery) {
                        $validateResponse = $demandeLivraisonService->validateDLAfterCheck($entityManager, $demande, false, true);
                        if ($validateResponse['success']) {
                            $preparation = $demande->getPreparations()->first();

                            /** @var Utilisateur $currentUser */
                            $currentUser = $this->getUser();
                            $articlesNotPicked = $preparationsManagerService->createMouvementsPrepaAndSplit($preparation, $currentUser, $entityManager);

                            $dateEnd = new DateTime('now');
                            $delivery = $livraisonsManagerService->createLivraison($dateEnd, $preparation, $entityManager);

                            $locationEndPreparation = $demande->getDestination();

                            $preparationsManagerService->treatPreparation($preparation, $this->getUser(), $locationEndPreparation, $articlesNotPicked);
                            $preparationsManagerService->closePreparationMouvement($preparation, $dateEnd, $locationEndPreparation);

                            $mouvementRepository = $entityManager->getRepository(MouvementStock::class);
                            $mouvements = $mouvementRepository->findByPreparation($preparation);
                            $entityManager->flush();

                            foreach ($mouvements as $mouvement) {
                                $preparationsManagerService->createMouvementLivraison(
                                    $mouvement->getQuantity(),
                                    $currentUser,
                                    $delivery,
                                    !empty($mouvement->getRefArticle()),
                                    $mouvement->getRefArticle() ?? $mouvement->getArticle(),
                                    $preparation,
                                    false,
                                    $locationEndPreparation
                                );
                            }
                        }
                    }
                }

                if (!isset($demande)
                    || !($demande instanceof Demande)) {
                    if (isset($demande)
                        && is_array($demande)) {
                        $demande = $demande['demande'];
                    }
                    else {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => 'Erreur lors de la création de la demande de livraison.'
                        ]);
                    }
                }
            }
            else if ($needCreateTransfer) {
                $toTreatRequest = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_REQUEST, TransferRequest::TO_TREAT);
                $toTreatOrder = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::TRANSFER_ORDER, TransferOrder::TO_TREAT);
                $origin = $emplacementRepository->find($data['origin']);
                $destination = $emplacementRepository->find($data['storage']);

                /** @var Utilisateur $requester */
                $requester = $this->getUser();
                $request = $transferRequestService->createTransferRequest($entityManager, $toTreatRequest, $origin, $destination, $requester);

                $request
                    ->setReception($reception)
                    ->setValidationDate($now);

                $order = $transferOrderService->createTransferOrder($entityManager, $toTreatOrder, $request);;

                $entityManager->persist($request);
                $entityManager->persist($order);

                try {
                    $entityManager->flush();
                }
                /** @noinspection PhpRedundantCatchClauseInspection */
                catch (UniqueConstraintViolationException $e) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Une autre demande de transfert est en cours de création, veuillez réessayer.'
                    ]);
                }
            }

            $receptionLocation = $reception->getLocation();
            // crée les articles et les ajoute à la demande, à la réception, crée les urgences
            $receptionLocationId = isset($receptionLocation) ? $receptionLocation->getId() : null;
            $emergencies = [];

            foreach($articles as $article) {
                if(isset($receptionLocationId)) {
                    $article['emplacement'] = $receptionLocationId;
                }

                $noCommande = isset($article['noCommande']) ? $article['noCommande'] : null;
                if ($request) {
                    $article['statut'] = Article::STATUT_EN_TRANSIT;
                }

                $article = $this->articleDataService->newArticle($article, $entityManager, $demande);
                if ($request) {
                    $request->addArticle($article);
                    $article->setQuantiteAPrelever($article->getQuantite());
                }

                $ref = $article->getArticleFournisseur()->getReferenceArticle();
                $rra = $receptionReferenceArticleRepository->findOneByReceptionAndCommandeAndRefArticleId($reception, $noCommande, $ref->getId());
                $article->setReceptionReferenceArticle($rra);
                $ref = $rra->getReferenceArticle();
                if($ref->getIsUrgent()) {
                    $emergencies[] = $article;
                }

                $mouvementStock = $mouvementStockService->createMouvementStock(
                    $currentUser,
                    null,
                    $article->getQuantite(),
                    $article,
                    MouvementStock::TYPE_ENTREE
                );
                $mouvementStock->setReceptionOrder($reception);

                $mouvementStockService->finishMouvementStock(
                    $mouvementStock,
                    $now,
                    $receptionLocation
                );

                $entityManager->persist($mouvementStock);

                $createdMvt = $trackingMovementService->createTrackingMovement(
                    $article->getBarCode(),
                    $receptionLocation,
                    $currentUser,
                    $now,
                    false,
                    true,
                    TrackingMovement::TYPE_DEPOSE,
                    [
                        'mouvementStock' => $mouvementStock,
                        'quantity' => $mouvementStock->getQuantity(),
                        'from' => $reception
                    ]
                );

                $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                $entityManager->persist($createdMvt);
                $entityManager->flush();
            }

            foreach($emergencies as $article) {
                $ref = $article->getReceptionReferenceArticle()->getReferenceArticle();

                $mailContent = $this->renderView('mails/contents/mailArticleUrgentReceived.html.twig', [
                    'emergency' => $ref->getEmergencyComment(),
                    'article' => $article,
                    'title' => 'Votre article urgent a bien été réceptionné.',
                ]);

                $destinataires = '';
                $userThatTriggeredEmergency = $ref->getUserThatTriggeredEmergency();
                if($userThatTriggeredEmergency) {
                    if(isset($demande) && $demande->getUtilisateur()) {
                        $destinataires = [
                            $userThatTriggeredEmergency,
                            $demande->getUtilisateur()
                        ];
                    } else {
                        $destinataires = [$userThatTriggeredEmergency];
                    }
                } else {
                    if(isset($demande) && $demande->getUtilisateur()) {
                        $destinataires = [$demande->getUtilisateur()];
                    }
                }

                if(!empty($destinataires)) {
                    // on envoie un mail aux demandeurs
                    $this->mailerService->sendMail(
                        'FOLLOW GT // Article urgent réceptionné', $mailContent,
                        $destinataires
                    );
                }

                $ref
                    ->setIsUrgent(false)
                    ->setUserThatTriggeredEmergency(null)
                    ->setEmergencyComment('');
            }

            if(isset($demande) && $demande->getType()->getSendMail()) {
                $nowDate = new DateTime('now');
                $this->mailerService->sendMail(
                    'FOLLOW GT // Réception d\'un colis ' . 'de type «' . $demande->getType()->getLabel() . '».',
                    $this->renderView('mails/contents/mailDemandeLivraisonValidate.html.twig', [
                        'demande' => $demande,
                        'fournisseur' => $reception->getFournisseur(),
                        'isReception' => true,
                        'reception' => $reception,
                        'title' => 'Une ' . $translator->trans('réception.réception')
                            . ' '
                            . $reception->getNumber()
                            . ' de type «'
                            . $demande->getType()->getLabel()
                            . '» a été réceptionnée le '
                            . $nowDate->format('d/m/Y \à H:i')
                            . '.',
                    ]),
                    $demande->getUtilisateur()
                );
            }
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La réception a bien été effectuée.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @param Litige $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function createAttachmentsForEntity(Litige $entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager) {
        $attachments = $attachmentService->createAttachements($request->files);
        foreach($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachment($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param ArticleDataService $articleDataService
     * @param string $articlesParamStr
     * @param Litige $dispute
     * @return Response|null
     * @throws NonUniqueResultException
     */
    private function addArticleIntoDispute(EntityManagerInterface $entityManager,
                                           ArticleDataService $articleDataService,
                                           string $articlesParamStr,
                                           Litige $dispute): ?Response {
        if(!empty($articlesParamStr)) {
            $articleRepository = $entityManager->getRepository(Article::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $articleStatusAvailable = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_ACTIF);
            $articleStatusDispute = $statutRepository->findOneByCategorieNameAndStatutCode(Article::CATEGORIE, Article::STATUT_EN_LITIGE);

            // on détache les colis existants...
            $existingArticles = $dispute->getArticles();
            foreach($existingArticles as $article) {
                $article->removeLitige($dispute);
                $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
            }

            // ... et on ajoute ceux sélectionnés
            $listArticlesId = explode(',', $articlesParamStr);
            foreach($listArticlesId as $articleId) {
                $article = $articleRepository->find($articleId);
                $dispute->addArticle($article);
                $ligneIsUrgent = $article->getReceptionReferenceArticle() && $article->getReceptionReferenceArticle()->getEmergencyTriggered();
                if($ligneIsUrgent) {
                    $dispute->setEmergencyTriggered(true);
                }

                if(!$articleDataService->articleCanBeAddedInDispute($article)) {
                    return new JsonResponse([
                        'success' => false,
                        'msg' => 'Les articles doivent être en statut "disponible" ou "en litige".'
                    ]);
                } else {
                    if($dispute->getStatus()->isTreated()) {
                        $this->setArticleStatusForTreatedDispute($article, $articleStatusAvailable);
                    } else { // !$dispute->getStatus()->isTreated()
                        $article->setStatut($articleStatusDispute);
                    }
                }
            }
        }
        return null;
    }

    public function setArticleStatusForTreatedDispute(Article $article,
                                                      Statut $articleStatusAvailable) {
        // on check si l'article a des
        $currentDisputesCounter = $article
            ->getLitiges()
            ->filter(function(Litige $articleDispute) {
                return !$articleDispute->getStatus()->isTreated();
            })
            ->count();

        if($currentDisputesCounter === 0) {
            $article->setStatut($articleStatusAvailable);
        }
    }

    /**
     * @Route("/{reception}/etiquette/{article}", name="reception_article_single_bar_code_print", options={"expose"=true})
     * @param Article $article
     * @param Reception $reception
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSingleReceptionArticleBarCode(Article $article,
                                                    Reception $reception,
                                                    ArticleDataService $articleDataService,
                                                    PDFGeneratorService $PDFGeneratorService): Response {
        $barcodeConfigs = [$articleDataService->getBarcodeConfig($article, $reception)];
        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'article');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

}
