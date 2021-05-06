<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\TrackingMovement;
use App\Entity\ParametrageGlobal;
use App\Entity\Attachment;

use App\Entity\Statut;
use App\Entity\Utilisateur;

use App\Service\AttachmentService;
use App\Service\CSVExportService;
use App\Service\FilterSupService;
use App\Service\FreeFieldService;
use App\Service\TrackingMovementService;
use App\Service\SpecificService;
use App\Service\UserService;

use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @Route("/mouvement-traca")
 */
class TrackingMovementController extends AbstractController
{

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * TrackingMovementController constructor.
     * @param AttachmentService $attachmentService
     * @param UserService $userService
     */
    public function __construct(AttachmentService $attachmentService,
                                UserService $userService)
    {
        $this->userService = $userService;
        $this->attachmentService = $attachmentService;
    }

    /**
     * @Route("/", name="mvt_traca_index", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param FilterSupService $filterSupService
     * @param TrackingMovementService $trackingMovementService
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(Request $request,
                          EntityManagerInterface $entityManager,
                          FilterSupService $filterSupService,
                          TrackingMovementService $trackingMovementService) {
        if(!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }

        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $champLibreRepository = $entityManager->getRepository(FreeField::class);

        $packFilter = $request->query->get('colis');
        if (!empty($packFilter)) {
            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();
            $filtreSupRepository->clearFiltersByUserAndPage($loggedUser, FiltreSup::PAGE_MVT_TRACA);
            $entityManager->flush();
            $filter = $filterSupService->createFiltreSup(FiltreSup::PAGE_MVT_TRACA, FiltreSup::FIELD_COLIS, $packFilter, $loggedUser);
            $entityManager->persist($filter);
            $entityManager->flush();
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();
        $fields = $trackingMovementService->getVisibleColumnsConfig($entityManager, $currentUser);

        $redirectAfterTrackingMovementCreation = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT);

        return $this->render('mouvement_traca/index.html.twig', [
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
            'redirectAfterTrackingMovementCreation' => (int)($redirectAfterTrackingMovementCreation ? !$redirectAfterTrackingMovementCreation->getValue() : true),
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
            'fields' => $fields
        ]);
    }

    private function errorWithDropOff($pack, $location, $packTranslation, $natureTranslation) {
        $bold = '<span class="font-weight-bold"> ';
        return 'Le ' . $packTranslation . $bold . $pack . '</span> ne dispose pas des ' . $natureTranslation . ' pour être déposé sur l\'emplacement' . $bold . $location . '</span>.';
    }

    /**
     * @Route("/api-columns", name="tracking_movement_api_columns", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TrackingMovementService $trackingMovementService
     * @return Response
     */
    public function apiColumns(Request $request,
                               EntityManagerInterface $entityManager,
                               TrackingMovementService $trackingMovementService): Response {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            /** @var Utilisateur $currentUser */
            $currentUser = $this->getUser();

            $columns = $trackingMovementService->getVisibleColumnsConfig($entityManager, $currentUser);

            return $this->json(array_values($columns));
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_tracking_movement", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function saveColumnVisible(Request $request,
                                      EntityManagerInterface $entityManager): Response {

        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }

        $data = json_decode($request->getContent(), true);
        $fields = array_keys($data);
        $fields[] = "actions";
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $currentUser->setColumnsVisibleForTrackingMovement($fields);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Vos préférences de colonnes ont bien été sauvegardées."
        ]);
    }

    /**
     * @Route("/creer", name="mvt_traca_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param TrackingMovementService $trackingMovementService
     * @param FreeFieldService $freeFieldService
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @return Response
     * @throws Exception
     */
    public function new(Request $request,
                        TrackingMovementService $trackingMovementService,
                        FreeFieldService $freeFieldService,
                        EntityManagerInterface $entityManager,
                        TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }
            $countCreatedMouvements = 0;
            $post = $request->request;
            $forced = $post->get('forced', false);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            $packTranslation = $translator->trans('arrivage.colis');
            $natureTranslation = $translator->trans('natures.natures requises');

            $operatorId = $post->get('operator');
            if (!empty($operatorId)) {
                $operator = $utilisateurRepository->find($operatorId);
            }
            if (empty($operator)) {
                /** @var Utilisateur $operator */
                $operator = $this->getUser();
            }

            $colisStr = $post->get('colis');
            $commentaire = $post->get('commentaire');
            $quantity = $post->getInt('quantity') ?: 1;

            if ($quantity < 1) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La quantité doit être supérieure à 0.'
                ]);
            }

            $date = new DateTime($post->get('datetime') ?: 'now', new DateTimeZone('Europe/Paris'));
            $fromNomade = false;
            $fileBag = $request->files->count() > 0 ? $request->files : null;

            $codeToPack = [];
            $createdMouvements = [];
            try {
                if (!empty($post->get('is-group'))) {
                    $groupTreatment = $trackingMovementService->handleGroups($post->all(), $entityManager, $operator);
                    if (!$groupTreatment['success']) {
                        return $this->json($groupTreatment);
                    }

                    $createdMouvements = $groupTreatment['createdMovements'];
                } else {
                    if (empty($post->get('is-mass'))) {
                        $emplacement = $emplacementRepository->find($post->get('emplacement'));
                        $createdMvt = $trackingMovementService->createTrackingMovement(
                            $colisStr,
                            $emplacement,
                            $operator,
                            $date,
                            $fromNomade,
                            null,
                            $post->getInt('type'),
                            [
                                'commentaire' => $commentaire,
                                'quantity' => $quantity,
                                'onlyPack' => true
                            ]
                        );

                        $associatedPack = $createdMvt->getPack();
                        $associatedGroup = $associatedPack->getParent();

                        if (!$forced && $associatedGroup) {
                            return $this->json([
                                'group' => $associatedGroup->getCode(),
                                'success' => true
                            ]);
                        } else if ($forced) {
                            $associatedPack->setParent(null);
                            $countCreatedMouvements++;
                        }

                        $movementType = $createdMvt->getType();
                        $movementTypeName = $movementType ? $movementType->getNom() : null;

                        // Dans le cas d'une dépose, on vérifie si l'emplacement peut accueillir le colis
                        if ($movementTypeName === TrackingMovement::TYPE_DEPOSE && !$emplacement->ableToBeDropOff($createdMvt->getPack())) {
                            return new JsonResponse([
                                'success' => false,
                                'msg' => $this->errorWithDropOff($colisStr, $emplacement, $packTranslation, $natureTranslation)
                            ]);
                        }
                        $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                        $entityManager->persist($createdMvt);
                        $createdMouvements[] = $createdMvt;
                    }
                    else {
                        $colisArray = explode(',', $colisStr);
                        $emplacementPrise = $emplacementRepository->find($post->get('emplacement-prise'));
                        $emplacementDepose = $emplacementRepository->find($post->get('emplacement-depose'));
                        foreach ($colisArray as $colis) {
                            $createdMvt = $trackingMovementService->createTrackingMovement(
                                $codeToPack[$colis] ?? $colis,
                                $emplacementPrise,
                                $operator,
                                $date,
                                $fromNomade,
                                true,
                                TrackingMovement::TYPE_PRISE,
                                [
                                    'commentaire' => $commentaire,
                                    'quantity' => $quantity,
                                    'onlyPack' => true
                                ]
                            );
                            $associatedPack = $createdMvt->getPack();
                            if ($associatedPack) {
                                $associatedGroup = $associatedPack->getParent();

                                if (!$forced && $associatedGroup) {
                                    return $this->json([
                                        'group' => $associatedGroup->getCode(),
                                        'success' => true,
                                    ]);
                                }
                                else if ($forced) {
                                    $associatedPack->setParent(null);
                                    $countCreatedMouvements++;
                                }
                            }
                            $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                            $entityManager->persist($createdMvt);
                            $createdMouvements[] = $createdMvt;
                            $createdPack = $createdMvt->getPack();
                            if ($createdPack) {
                                $createdMvt = $trackingMovementService->createTrackingMovement(
                                    $createdPack,
                                    $emplacementDepose,
                                    $operator,
                                    $date,
                                    $fromNomade,
                                    true,
                                    TrackingMovement::TYPE_DEPOSE,
                                    [
                                        'commentaire' => $commentaire,
                                        'quantity' => $quantity
                                    ]
                                );

                                // Dans le cas d'une dépose, on vérifie si l'emplacement peut accueillir le colis
                                if (!$emplacementDepose->ableToBeDropOff($createdPack)) {
                                    return new JsonResponse([
                                        'success' => false,
                                        'msg' => $this->errorWithDropOff($createdPack->getCode(), $emplacementDepose, $packTranslation, $natureTranslation)
                                    ]);
                                }

                                $trackingMovementService->persistSubEntities($entityManager, $createdMvt);
                                $entityManager->persist($createdMvt);
                                $createdMouvements[] = $createdMvt;
                                $codeToPack[$colis] = $createdPack;
                            }
                        }
                    }
                }
            } catch (Exception $exception) {
                return $this->json([
                    'success' => false,
                    'msg' => $exception->getMessage() === Pack::PACK_IS_GROUP
                        ? 'Le colis scanné est un groupe.'
                        : 'Une erreur inconnue s\'est produite.'
                ]);
            }

            if (isset($fileBag)) {
                $fileNames = [];
                foreach ($fileBag->all() as $file) {
                    $fileNames = array_merge(
                        $fileNames,
                        $this->attachmentService->saveFile($file)
                    );
                }
                foreach ($createdMouvements as $mouvement) {
                    $this->persistAttachments($mouvement, $this->attachmentService, $fileNames, $entityManager);
                }
            }

            foreach ($createdMouvements as $mouvement) {
                $freeFieldService->manageFreeFields($mouvement, $post->all(), $entityManager);
            }
            $countCreatedMouvements += count($createdMouvements);
            $entityManager->flush();

            return new JsonResponse([
                'success' => $countCreatedMouvements > 0,
                'group' => null,
                'trackingMovementsCounter' => $countCreatedMouvements
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="tracking_movement_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param TrackingMovementService $trackingMovementService
     * @return Response
     * @throws Exception
     */
    public function api(Request $request, TrackingMovementService $trackingMovementService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $trackingMovementService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="tracking_movement_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function editApi(EntityManagerInterface $entityManager,
                            Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            $champLibreRepository = $entityManager->getRepository(FreeField::class);

            $trackingMovement = $trackingMovementRepository->find($data['id']);

            $json = $this->renderView('mouvement_traca/modalEditMvtTracaContent.html.twig', [
                'mvt' => $trackingMovement,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
                'attachments' => $trackingMovement->getAttachments(),
                'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::MOUVEMENT_TRACA]),
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="mvt_traca_edit", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param FreeFieldService $freeFieldService
     * @param Request $request
     * @return Response
     */
    public function edit(EntityManagerInterface $entityManager,
                         FreeFieldService $freeFieldService,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            $operator = $utilisateurRepository->find($post->get('operator'));
            $quantity = $post->getInt('quantity') ?: 1;

            if ($quantity < 1) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La quantité doit être supérieure à 0.'
                ]);
            }

            /** @var TrackingMovement $mvt */
            $mvt = $trackingMovementRepository->find($post->get('id'));
            $mvt
                ->setOperateur($operator)
                ->setQuantity($quantity)
                ->setCommentaire($post->get('commentaire'));

            $entityManager->flush();

            $listAttachmentIdToKeep = $post->get('files');

            $attachments = $mvt->getAttachments()->toArray();
            foreach ($attachments as $attachment) {
                /** @var Attachment $attachment */
                if (!$listAttachmentIdToKeep || !in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, $mvt);
                }
            }

            $this->persistAttachments($mvt, $this->attachmentService, $request->files, $entityManager);
            $freeFieldService->manageFreeFields($mvt, $post->all(), $entityManager);

            $entityManager->flush();

            return new JsonResponse([
                'success' => true
            ]);
        }
        throw new BadRequestHttpException();
    }


    /**
     * @Route("/supprimer", name="mvt_traca_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            /** @var TrackingMovement $trackingMovement */
            $trackingMovement = $trackingMovementRepository->find($data['mvt']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            if($trackingMovement) {
                $entityManager->remove($trackingMovement);
                $entityManager->flush();
            }

            return $this->json([
                "success" => true,
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_mouvements_traca_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param FreeFieldService $freeFieldService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Exception
     */
    public function getTrackingMovementCSV(Request $request,
                                           CSVExportService $CSVExportService,
                                           FreeFieldService $freeFieldService,
                                           EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
            $attachmentRepository = $entityManager->getRepository(Attachment::class);

            $freeFieldsConfig = $freeFieldService->createExportArrayConfig($entityManager, [CategorieCL::MVT_TRACA]);

            $trackingMovements = $trackingMovementRepository->getByDates($dateTimeMin, $dateTimeMax);
            $attachmentsNameByTracking = $attachmentRepository->getNameGroupByMovements();

            $csvHeader = array_merge([
                'date',
                'colis',
                'emplacement',
                'quantité',
                'type',
                'opérateur',
                'commentaire',
                'pieces jointes',
                'origine',
                'numéro de commande',
                'urgence'
            ], $freeFieldsConfig['freeFieldsHeader']);

            return $CSVExportService->createBinaryResponseFromData(
                'export_mouvement_traca.csv',
                $trackingMovements,
                $csvHeader,
                function ($movement) use ($attachmentsNameByTracking, $freeFieldsConfig, $freeFieldService) {
                    $row = [];
                    $row[] = $movement['datetime'] ? $movement['datetime']->format('d/m/Y H:i') : '';
                    $row[] = $movement['code'];
                    $row[] = $movement['locationLabel'] ?: '';
                    $row[] = $movement['quantity'] ?: '';
                    $row[] = $movement['typeName'] ?: '';
                    $row[] = $movement['operatorUsername'] ?: '';
                    $row[] = $movement['commentaire'] ? strip_tags($movement['commentaire']) : '';
                    $row[] = $attachmentsNameByTracking[(int)$movement['id']] ?? '';
                    $row[] = $movement['numeroArrivage'] ?: $movement['receptionNumber'] ?: '';
                    $row[] = $movement['numeroCommandeListArrivage'] && !empty($movement['numeroCommandeListArrivage'])
                        ? implode(', ', $movement['numeroCommandeListArrivage'])
                        : ($movement['orderNumber'] ?: '');
                    $row[] = !empty($movement['isUrgent']) ? 'oui' : 'non';

                    foreach ($freeFieldsConfig['freeFieldIds'] as $freeFieldId) {
                        $row[] = $freeFieldService->serializeValue([
                            'typage' => $freeFieldsConfig['freeFieldsIdToTyping'][$freeFieldId],
                            'valeur' => $movement['freeFields'][$freeFieldId] ?? ''
                        ]);
                    }

                    return [$row];
                }
            );
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/voir", name="mvt_traca_show", options={"expose"=true}, methods={"GET","POST"})
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @return Response
     */
    public function show(EntityManagerInterface $entityManager,
                         Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $statutRepository = $entityManager->getRepository(Statut::class);
            $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);

            $trackingMovement = $trackingMovementRepository->find($data);
            $json = $this->renderView('mouvement_traca/modalShowMvtTracaContent.html.twig', [
                'mvt' => $trackingMovement,
                'statuts' => $statutRepository->findByCategorieName(CategorieStatut::MVT_TRACA),
                'attachments' => $trackingMovement->getAttachments()
            ]);
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/obtenir-corps-modal-nouveau", name="mouvement_traca_get_appropriate_html", options={"expose"=true}, methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SpecificService $specificService
     * @return Response
     */
    public function getAppropriateHtml(Request $request,
                                       EntityManagerInterface $entityManager,
                                       SpecificService $specificService): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
            $statutRepository = $entityManager->getRepository(Statut::class);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $templateDirectory = 'mouvement_traca';

            if ($typeId === 'fromStart') {
                $currentClient = $specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);
                $fileToRender = "$templateDirectory/" . (
                    $currentClient
                        ? 'newMassMvtTraca.html.twig'
                        : 'newSingleMvtTraca.html.twig'
                    );
            } else {
                $appropriateType = $statutRepository->find($typeId);
                if ($appropriateType && $appropriateType->getNom() === TrackingMovement::TYPE_PRISE_DEPOSE) {
                    $fileToRender = "$templateDirectory/newMassMvtTraca.html.twig";
                }
                else if ($appropriateType && $appropriateType->getNom() === TrackingMovement::TYPE_GROUP) {
                    $fileToRender = "$templateDirectory/newGroupMvtTraca.html.twig";
                }
                else {
                    $fileToRender = "$templateDirectory/newSingleMvtTraca.html.twig";
                }
            }
            return new JsonResponse([
                'modalBody' => $fileToRender === 'mouvement_traca/' ? false : $this->renderView($fileToRender, []),
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @param TrackingMovement $trackingMovement
     * @param AttachmentService $attachmentService
     * @param FileBag|array $files
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachments(TrackingMovement $trackingMovement, AttachmentService $attachmentService, $files, EntityManagerInterface $entityManager)
    {
        $attachments = $attachmentService->createAttachements($files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $trackingMovement->addAttachment($attachment);
        }
    }
}
