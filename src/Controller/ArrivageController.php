<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Chauffeur;
use App\Entity\Colis;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Litige;
use App\Entity\LitigeHistoric;
use App\Entity\Menu;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Entity\Urgence;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Repository\PieceJointeRepository;
use App\Repository\TransporteurRepository;
use App\Service\ArrivageDataService;
use App\Service\AttachmentService;
use App\Service\ColisService;
use App\Service\CSVExportService;
use App\Service\DashboardService;
use App\Service\GlobalParamService;
use App\Service\LitigeService;
use App\Service\PDFGeneratorService;
use App\Service\SpecificService;
use App\Service\StatutService;
use App\Service\UserService;
use App\Service\MailerService;
use App\Service\ValeurChampLibreService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/arrivage")
 */
class ArrivageController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var TransporteurRepository
     */
    private $transporteurRepository;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    /**
     * @var MailerService
     */
    private $mailerService;

    /**
     * @var PieceJointeRepository
     */
    private $pieceJointeRepository;

    /**
     * @var SpecificService
     */
    private $specificService;

    /**
     * @var AttachmentService
     */
    private $attachmentService;

    /**
     * @var ArrivageDataService
     */
    private $arrivageDataService;

    /**
     * @var DashboardService
     */
    private $dashboardService;

    public function __construct(ArrivageDataService $arrivageDataService,
                                DashboardService $dashboardService,
                                AttachmentService $attachmentService,
                                PieceJointeRepository $pieceJointeRepository,
                                SpecificService $specificService,
                                MailerService $mailerService,
                                GlobalParamService $globalParamService,
                                TransporteurRepository $transporteurRepository,
                                UserService $userService)
    {
        $this->dashboardService = $dashboardService;
        $this->specificService = $specificService;
        $this->globalParamService = $globalParamService;
        $this->userService = $userService;
        $this->transporteurRepository = $transporteurRepository;
        $this->mailerService = $mailerService;
        $this->pieceJointeRepository = $pieceJointeRepository;
        $this->attachmentService = $attachmentService;
        $this->arrivageDataService = $arrivageDataService;
    }

    /**
     * @Route("/", name="arrivage_index")
     * @param EntityManagerInterface $entityManager
     * @param TranslatorInterface $translator
     * @param StatutService $statutService
     * @return RedirectResponse|Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager,
                          TranslatorInterface $translator,
                          StatutService $statutService)
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
            return $this->redirectToRoute('access_denied');
        }

        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
        $transporteurRepository = $entityManager->getRepository(Transporteur::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        $user = $this->getUser();
        $champs = [
            ["key" => 'date', 'label' => 'Date'],
            ["key" => 'numeroArrivage', 'label' => $translator->trans('arrivage.n° d\'arrivage')],
            ["key" => 'transporteur', 'label' => 'Transporteur'],
            ["key" => 'chauffeur', 'label' => 'Chauffeur'],
            ["key" => 'noTracking', 'label' => 'N° tracking transporteur'],
            ["key" => 'NumeroCommandeList', 'label' => 'N° commande / BL'],
            ["key" => 'fournisseur', 'label' => 'Fournisseur'],
            ["key" => 'destinataire', 'label' => $translator->trans('arrivage.destinataire')],
            ["key" => 'acheteurs', 'label' => $translator->trans('arrivage.acheteurs')],
            ["key" => 'NbUM', 'label' => 'Nb UM'],
            ["key" => 'duty', 'label' => 'Douane'],
            ["key" => 'frozen', 'label' => 'Congelé'],
            ["key" => 'Statut', 'label' => 'Statut'],
            ["key" => 'Utilisateur', 'label' => 'Utilisateur'],
            ["key" => 'urgent', 'label' => 'Urgent'],
        ];

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);
        $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
        $paramGlobalDefaultStatusArrivageId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_STATUT_ARRIVAGE);

        $status = $statutService->findAllStatusArrivage();

        return $this->render('arrivage/index.html.twig', [
            'carriers' => $transporteurRepository->findAllSorted(),
            'chauffeurs' => $chauffeurRepository->findAllSorted(),
            'users' => $utilisateurRepository->findAllSorted(),
            'fournisseurs' => $fournisseurRepository->findAllSorted(),
            'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
            'natures' => $natureRepository->findAll(),
            'statuts' => $status,
            'fieldsParam' => $fieldsParam,
            'redirect' => $paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true,
            'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
            'pageLengthForArrivage' => $this->getUser()->getPageLengthForArrivage() ?: 10,
            'autoPrint' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::AUTO_PRINT_COLIS),
            'defaultStatutArrivageId' => $paramGlobalDefaultStatusArrivageId,
            'champs' => $champs,
            'columnsVisibles' => $user->getColumnsVisibleForArrivage(),
        ]);
    }

    /**
     * @Route("/api", name="arrivage_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }

            $canSeeAll = $this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL);
            $userId = $canSeeAll ? null : ($this->getUser() ? $this->getUser()->getId() : null);
            $data = $this->arrivageDataService->getDataForDatatable($request->request, $userId);
            $user = $this->getUser();
            $data['visible'] = $user->getColumnsVisibleForArrivage();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/creer", name="arrivage_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param AttachmentService $attachmentService
     * @param UserService $userService
     * @param ArrivageDataService $arrivageDataService
     * @param ValeurChampLibreService $valeurChampLibreService
     * @param ColisService $colisService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        AttachmentService $attachmentService,
                        UserService $userService,
                        ArrivageDataService $arrivageDataService,
                        ValeurChampLibreService $valeurChampLibreService,
                        ColisService $colisService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $request->request->all();
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $transporteurRepository = $entityManager->getRepository(Transporteur::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $userRepository = $entityManager->getRepository(Utilisateur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $sendMail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL);

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
            $numeroArrivage = $date->format('ymdHis');

            $arrivage = new Arrivage();
            $arrivage
                ->setIsUrgent(false)
                ->setDate($date)
                ->setStatut($statutRepository->find($data['statut']))
                ->setUtilisateur($this->getUser())
                ->setNumeroArrivage($numeroArrivage)
                ->setDuty(isset($data['duty']) ? $data['duty'] == 'true' : false)
                ->setFrozen(isset($data['frozen']) ? $data['frozen'] == 'true' : false)
                ->setCommentaire($data['commentaire'] ?? null);

            if (!empty($data['fournisseur'])) {
                $arrivage->setFournisseur($fournisseurRepository->find($data['fournisseur']));
            }
            if (!empty($data['transporteur'])) {
                $arrivage->setTransporteur($transporteurRepository->find($data['transporteur']));
            }
            if (!empty($data['chauffeur'])) {
                $arrivage->setChauffeur($chauffeurRepository->find($data['chauffeur']));
            }
            if (!empty($data['noTracking'])) {
                $arrivage->setNoTracking(substr($data['noTracking'], 0, 64));
            }
            $numeroCommandeList = explode(',', $data['numeroCommandeList'] ?? '');
            if (!empty($numeroCommandeList)) {
                $arrivage->setNumeroCommandeList($numeroCommandeList);
            }
            if (!empty($data['destinataire'])) {
                $arrivage->setDestinataire($userRepository->find($data['destinataire']));
            }
            if (!empty($data['acheteurs'])) {
                $acheteursId = explode(',', $data['acheteurs']);
                foreach ($acheteursId as $acheteurId) {
                    $arrivage->addAcheteur($userRepository->find($acheteurId));
                }
            }


            $this->persistAttachmentsForEntity($arrivage, $attachmentService, $request, $entityManager);
            $colis = isset($data['colis']) ? json_decode($data['colis'], true) : [];
            $natures = [];
            foreach ($colis as $key => $value) {
                if (isset($value)) {
                    $natures[intval($key)] = intval($value);
                }
            }
            $total = array_reduce($natures, function (int $carry, $nature) {
                return $carry + $nature;
            }, 0);

            if ($total === 0) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Veuillez renseigner au moins un colis.<br>"
                ]);
            }
            $colisService->persistMultiColis($arrivage, $natures, $this->getUser());


            $champsLibresKey = array_keys($data);
            foreach ($champsLibresKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = $valeurChampLibreService->createValeurChampLibre($champs, $data[$champs]);
                    $valeurChampLibre->addArrivage($arrivage);
                    $entityManager->persist($valeurChampLibre);
                    $arrivage->addValeurChampLibre($valeurChampLibre);
                }
            }

            $alertConfigs = $arrivageDataService->processEmergenciesOnArrival($arrivage);

            $entityManager->flush();
            if ($sendMail) {
                $arrivageDataService->sendArrivalEmails($arrivage);
            }

            $entityManager->flush();
            $paramGlobalRedirectAfterNewArrivage = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL);
            $statutConformeId = $statutRepository->getOneIdByCategorieNameAndStatusName(CategorieStatut::ARRIVAGE, Arrivage::STATUS_CONFORME);

            $data = [
                "redirectAfterAlert" => ($paramGlobalRedirectAfterNewArrivage ? $paramGlobalRedirectAfterNewArrivage->getValue() : true)
                    ? $this->generateUrl('arrivage_show', ['id' => $arrivage->getId()])
                    : null,
                'printColis' => (isset($data['printColis']) && $data['printColis'] === 'true'),
                'printArrivage' => isset($data['printArrivage']) && $data['printArrivage'] === 'true',
                'arrivageId' => $arrivage->getId(),
                'numeroArrivage' => $arrivage->getNumeroArrivage(),
                'champsLibresBlock' => $this->renderView('arrivage/champsLibresArrivage.html.twig', [
                    'champsLibres' => $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]),
                ]),
                'statutConformeId' => $statutConformeId,
                'alertConfigs' => $alertConfigs
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="arrivage_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param StatutService $statutService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager,
                            StatutService $statutService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }

            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);

            $arrivage = $arrivageRepository->find($data['id']);

            // construction de la chaîne de caractères pour alimenter le select2
            $acheteursUsernames = [];
            foreach ($arrivage->getAcheteurs() as $acheteur) {
                $acheteursUsernames[] = $acheteur->getUsername();
            }
            $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

            $champsLibres = $champLibreRepository->findByCategoryTypeLabels([CategoryType::ARRIVAGE]);
            $champsLibresArray = [];
            foreach ($champsLibres as $champLibre) {
                $valeurChampArr = $valeurChampLibreRepository->getValueByArrivageAndChampLibre($arrivage, $champLibre);
                $champsLibresArray[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => $champLibre->getElements() ?? '',
                    'requiredEdit' => $champLibre->getRequiredEdit(),
                    'valeurChampLibre' => $valeurChampArr,
                    'edit' => true
                ];
            }

            $status = $statutService->findAllStatusArrivage();

            if ($this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {

                $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
                $typeRepository = $entityManager->getRepository(Type::class);
                $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
                $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

                $html = $this->renderView('arrivage/modalEditArrivageContent.html.twig', [
                    'arrivage' => $arrivage,
                    'attachements' => $this->pieceJointeRepository->findBy(['arrivage' => $arrivage]),
                    'utilisateurs' => $utilisateurRepository->findAllSorted(),
                    'fournisseurs' => $fournisseurRepository->findAllSorted(),
                    'transporteurs' => $this->transporteurRepository->findAllSorted(),
                    'chauffeurs' => $chauffeurRepository->findAllSorted(),
                    'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                    'statuts' => $status,
                    'fieldsParam' => $fieldsParam,
                    'champsLibres' => $champsLibresArray
                ]);
            } else {
                $html = '';
            }

            return new JsonResponse(['html' => $html, 'acheteurs' => $acheteursUsernames]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route(
     *     "/{arrival}/urgent",
     *     name="patch_arrivage_urgent",
     *     options={"expose"=true},
     *     methods="PATCH",
     *     condition="request.isXmlHttpRequest() && '%client%' == constant('\\App\\Service\\SpecificService::CLIENT_SAFRAN_ED')"
     * )
     *
     * @param Arrivage $arrival
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function patchUrgentArrival(Arrivage $arrival,
                                       Request $request,
                                       ArrivageDataService $arrivageDataService,
                                       EntityManagerInterface $entityManager): Response
    {
        $urgenceRepository = $entityManager->getRepository(Urgence::class);
        $numeroCommande = $request->request->get('numeroCommande');
        $postNb = $request->request->get('postNb');

        $urgencesMatching = !empty($numeroCommande)
            ? $urgenceRepository->findUrgencesMatching(
                $arrival->getDate(),
                $arrival->getFournisseur(),
                $numeroCommande,
                $postNb,
                true
            )
            : [];

        $success = !empty($urgencesMatching);

        if ($success) {
            $arrivageDataService->setArrivalUrgent($arrival, $urgencesMatching);
            $entityManager->flush();
        }

        $response = [
            'success' => $success,
            'alertConfigs' => $success
                ? [$arrivageDataService->createArrivalAlertConfig($arrival, false, $urgencesMatching)]
                : null
        ];

        return new JsonResponse($response);
    }

    /**
     * @Route("/modifier", name="arrivage_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param ValeurChampLibreService $valeurChampLibreService
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function edit(Request $request,
                         ArrivageDataService $arrivageDataService,
                         ValeurChampLibreService $valeurChampLibreService,
                         EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $statutRepository = $entityManager->getRepository(Statut::class);
            $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
            $champLibreRepository = $entityManager->getRepository(ChampLibre::class);
            $valeurChampLibreRepository = $entityManager->getRepository(ValeurChampLibre::class);
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $chauffeurRepository = $entityManager->getRepository(Chauffeur::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $post = $request->request;
            $isSEDCurrentClient = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED);

            $arrivage = $arrivageRepository->find($post->get('id'));

            $fournisseurId = $post->get('fournisseur');
            $transporteurId = $post->get('transporteur');
            $destinataireId = $post->get('destinataire');
            $statutId = $post->get('statut');
            $chauffeurId = $post->get('chauffeur');
            $newDestinataire = $destinataireId ? $utilisateurRepository->find($destinataireId) : null;
            $destinataireChanged = $newDestinataire && $newDestinataire !== $arrivage->getDestinataire();
            $numeroCommadeListStr = $post->get('numeroCommandeList');

            $sendMail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL);

            $arrivage
                ->setCommentaire($post->get('commentaire'))
                ->setNoTracking(substr($post->get('noTracking'), 0, 64))
                ->setNumeroCommandeList(explode(',', $numeroCommadeListStr))
                ->setFournisseur($fournisseurId ? $fournisseurRepository->find($fournisseurId) : null)
                ->setTransporteur($transporteurId ? $this->transporteurRepository->find($transporteurId) : null)
                ->setChauffeur($chauffeurId ? $chauffeurRepository->find($chauffeurId) : null)
                ->setStatut($statutId ? $statutRepository->find($statutId) : null)
                ->setDuty($post->get('duty') == 'true')
                ->setFrozen($post->get('frozen') == 'true')
                ->setDestinataire($newDestinataire);

            $acheteurs = $post->get('acheteurs');

            $acheteursEntities = array_map(function ($acheteur) use ($utilisateurRepository) {
                return $utilisateurRepository->findOneByUsername($acheteur);
            }, explode(',', $acheteurs));

            $arrivage->removeAllAcheteur();
            if (!empty($acheteurs)) {
                foreach ($acheteursEntities as $acheteursEntity) {
                    $arrivage->addAcheteur($acheteursEntity);
                }
            }
            $entityManager->flush();
            if ($sendMail && $destinataireChanged) {
                $arrivageDataService->sendArrivalEmails($arrivage);
            }


            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $arrivage->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, $arrivage);
                }
            }

            $this->persistAttachmentsForEntity($arrivage, $this->attachmentService, $request, $entityManager);

            $champLibreKey = array_keys($post->all());
            foreach ($champLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $champLibreRepository->find($champ);
                    $valeurChampLibre = $valeurChampLibreRepository->findOneByArrivageAndChampLibre($arrivage, $champLibre);
                    $value = $post->get($champ);
                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = $valeurChampLibreService->createValeurChampLibre($champLibre, $value);
                        $valeurChampLibre->addArrivage($arrivage);
                        $entityManager->persist($valeurChampLibre);
                    }
                    else {
                        $valeurChampLibreService->updateValue($valeurChampLibre, $value);
                    }
                    $entityManager->flush();
                }
            }

            $response = [
                'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                    'arrivage' => $arrivage,
                    'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
                    'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage)
                ]),
                'alertConfigs' => [
                    $arrivageDataService->createArrivalAlertConfig($arrivage, $isSEDCurrentClient)
                ]
            ];
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="arrivage_delete", options={"expose"=true},methods={"GET","POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
            $arrivage = $arrivageRepository->find($data['arrivage']);

            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $canBeDeleted = ($arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0);

            if ($canBeDeleted) {
                foreach ($arrivage->getColis() as $colis) {
                    $litiges = $colis->getLitiges();
                    foreach ($mouvementTracaRepository->getByColisAndPriorToDate($colis->getCode(), $arrivage->getDate()) as $mvtToDelete) {
                        foreach ($mvtToDelete->getLinkedPackLastDrops() as $pack) {
                            $pack->setLastDrop(null);
                        }
                        $entityManager->remove($mvtToDelete);
                    }
                    $entityManager->remove($colis);
                    foreach ($litiges as $litige) {
                        $entityManager->remove($litige);
                    }
                }
                foreach ($arrivage->getAttachements() as $attachement) {
                    $this->attachmentService->removeAndDeleteAttachment($attachement, $arrivage);
                }
                foreach ($arrivage->getUrgences() as $urgence) {
                    $urgence->setLastArrival(null);
                }
                $entityManager->remove($arrivage);
                $entityManager->flush();
                $data = [
                    "redirect" => $this->generateUrl('arrivage_index')
                ];
            } else {
                $data = false;
            }
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/depose-pj", name="arrivage_depose", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function depose(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $fileNames = [];

            $id = (int)$request->request->get('id');
            $arrivage = $arrivageRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    $pj = $this->attachmentService->createPieceJointe($file, $arrivage);
                    $entityManager->persist($pj);

                    $fileNames[] = [
                        'name' => $pj->getFileName(),
                        'originalName' => $file->getClientOriginalName()
                    ];
                }
            }
            $entityManager->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('attachment/attachmentLine.html.twig', [
                    'arrivage' => $arrivage,
                    'pjName' => $fileName['name'],
                    'originalName' => $fileName['originalName']
                ]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/ajoute-commentaire", name="add_comment",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function addComment(Request $request,
                               EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $response = '';

            // spécifique SAFRAN CERAMICS ajout de commentaire
            $isSafran = $this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_CS);
            if ($isSafran) {
                $typeRepository = $entityManager->getRepository(Type::class);
                $type = $typeRepository->find($data['typeLitigeId']);
                $response = $type->getDescription();
            }

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/lister-colis", name="arrivage_list_colis_api", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function listColisByArrivage(Request $request,
                                        EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $arrivage = $arrivageRepository->find($data['id']);

            $html = $this->renderView('arrivage/modalListColisContent.html.twig', [
                'arrivage' => $arrivage
            ]);

            return new JsonResponse($html);

        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/garder-pj", name="garder_pj", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function displayAttachmentForNew(Request $request, EntityManagerInterface $entityManager)
    {
        if ($request->isXmlHttpRequest()) {
            $fileNames = [];
            $html = '';
            $path = "../public/uploads/attachements/temp/";
            if (!file_exists($path)) {
                mkdir($path, 0777);
            }
            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    if ($file->getClientOriginalExtension()) {
                        $filename = uniqid() . "." . $file->getClientOriginalExtension();
                    } else {
                        $filename = uniqid();
                    }
                    $fileNames[] = $filename;
                    $file->move($path, $filename);
                    $html .= $this->renderView('attachment/attachmentLine.html.twig', [
                        'pjName' => $filename,
                        'originalName' => $file->getClientOriginalName()
                    ]);
                    $pj = new PieceJointe();
                    $pj
                        ->setOriginalName($file->getClientOriginalName())
                        ->setFileName($filename);
                    $entityManager->persist($pj);
                }
                $entityManager->flush();
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/csv", name="get_arrivages_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getArrivageCSV(Request $request,
                                   CSVExportService $CSVExportService,
                                   EntityManagerInterface $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        }
        catch(\Throwable $throwable) {}

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
            $natureRepository = $entityManager->getRepository(Nature::class);
            $colisRepository = $entityManager->getRepository(Colis::class);

            $colisData = $colisRepository->countColisByArrivageAndNature(
                [
                    $dateTimeMin->format('Y-m-d H:i:s'),
                    $dateTimeMax->format('Y-m-d H:i:s')
                ]
            );
            $arrivals = $arrivageRepository->getByDates($dateTimeMin, $dateTimeMax);
            $buyersByArrival = $utilisateurRepository->getUsernameBuyersGroupByArrival();
            $natureLabels = $natureRepository->findAllLabels();
            // en-têtes champs fixes
            $csvHeader = [
                'n° arrivage',
                'destinataire',
                'fournisseur',
                'transporteur',
                'chauffeur',
                'n° tracking transporteur',
                'n° commande/BL',
                'acheteurs',
                'douane',
                'congelé',
                'statut',
                'commentaire',
                'date',
                'utilisateur',
            ];
            $csvHeader = array_merge($csvHeader, $natureLabels);

            return $CSVExportService->createCsvResponse(
                'export.csv',
                $arrivals,
                $csvHeader,
                function ($arrival) use ($buyersByArrival, $natureLabels, $colisData) {
                    $arrivalId = (int) $arrival['id'];
                    $row = [];
                    $row[] = $arrival['numeroArrivage'] ?: '';
                    $row[] = $arrival['recipientUsername'] ?: '';
                    $row[] = $arrival['fournisseurName'] ?: '';
                    $row[] = $arrival['transporteurLabel'] ?: '';
                    $row[] = (!empty($arrival['chauffeurFirstname']) && !empty($arrival['chauffeurSurname']))
                        ? $arrival['chauffeurFirstname'] . ' ' . $arrival['chauffeurSurname']
                        : ($arrival['chauffeurFirstname'] ?: $arrival['chauffeurSurname'] ?: '');
                    $row[] = $arrival['noTracking'] ?: '';
                    $row[] = !empty($arrival['numeroCommandeList']) ? implode(' / ', $arrival['numeroCommandeList']) : '';
                    $row[] = $buyersByArrival[$arrivalId] ?? '';
                    $row[] = $arrival['duty'] ? 'oui' : 'non';
                    $row[] = $arrival['frozen'] ? 'oui' : 'non';
                    $row[] = $arrival['statusName'] ?: '';
                    $row[] = $arrival['commentaire'] ? strip_tags($arrival['commentaire']) : '';
                    $row[] = $arrival['date'] ? $arrival['date']->format('d/m/Y H:i:s') : '';
                    $row[] = $arrival['userUsername'] ?: '';

                    foreach ($natureLabels as $natureLabel) {
                        $count = (isset($colisData[$arrivalId]) && isset($colisData[$arrivalId][$natureLabel]))
                            ? $colisData[$arrivalId][$natureLabel]
                            : 0;
                        $row[] = $count;
                    }
                    return [$row];
                }
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/voir/{id}/{printColis}/{printArrivage}", name="arrivage_show", options={"expose"=true}, methods={"GET", "POST"})
     *
     * @param EntityManagerInterface $entityManager
     * @param ArrivageDataService $arrivageDataService
     * @param Arrivage $arrivage
     * @param bool $printColis
     * @param bool $printArrivage
     *
     * @return JsonResponse
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function show(EntityManagerInterface $entityManager,
                         ArrivageDataService $arrivageDataService,
                         Arrivage $arrivage,
                         bool $printColis = false,
                         bool $printArrivage = false): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::LIST_ALL)
            && !in_array($this->getUser(), $arrivage->getAcheteurs()->toArray())) {
            return $this->redirectToRoute('access_denied');
        }

        $paramGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $fieldsParamRepository = $entityManager->getRepository(FieldsParam::class);
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $natureRepository = $entityManager->getRepository(Nature::class);
        $usersRepository = $entityManager->getRepository(Utilisateur::class);

        $acheteursNames = [];
        foreach ($arrivage->getAcheteurs() as $user) {
            $acheteursNames[] = $user->getUsername();
        }

        $fieldsParam = $fieldsParamRepository->getByEntity(FieldsParam::ENTITY_CODE_ARRIVAGE);

        return $this->render("arrivage/show.html.twig",
            [
                'arrivage' => $arrivage,
                'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'acheteurs' => $acheteursNames,
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'allColis' => $arrivage->getColis(),
                'natures' => $natureRepository->findAll(),
                'printColis' => $printColis,
                'printArrivage' => $printArrivage,
                'utilisateurs' => $usersRepository->getIdAndLibelleBySearch(''),
                'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivage) == 0,
                'fieldsParam' => $fieldsParam,
                'defaultLitigeStatusId' => $paramGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_STATUT_LITIGE_ARR),
                'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivage)
            ]);
    }

    /**
     * @Route("/creer-litige", name="litige_new", options={"expose"=true}, methods={"POST"})
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param LitigeService $litigeService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function newLitige(Request $request,
                              ArrivageDataService $arrivageDataService,
                              LitigeService $litigeService,
                              EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $post = $request->request;

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $colisRepository = $entityManager->getRepository(Colis::class);
            $usersRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = new Litige();
            $litige
                ->setDeclarant($usersRepository->find($post->get('declarantLitige')))
                ->setStatus($statutRepository->find($post->get('statutLitige')))
                ->setType($typeRepository->find($post->get('typeLitige')))
                ->setCreationDate(new DateTime('now'));
            $arrivage = null;
            if (!empty($colis = $post->get('colisLitige'))) {
                $listColisId = explode(',', $colis);
                foreach ($listColisId as $colisId) {
                    $colis = $colisRepository->find($colisId);
                    $litige->addColis($colis);
                    $arrivage = $colis->getArrivage();
                }
            }
            if ($post->get('emergency')) {
                $litige->setEmergencyTriggered($post->get('emergency') === 'true');
            }
            if ((!$litige->getStatus() || !$litige->getStatus()->isTreated()) && $arrivage) {
                $arrivage->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARRIVAGE, Arrivage::STATUS_LITIGE));
            }
            $typeDescription = $litige->getType()->getDescription();
            $typeLabel = $litige->getType()->getLabel();
            $statutNom = $litige->getStatus()->getNom();

            $trimmedTypeDescription = trim($typeDescription);
            $userComment = trim($post->get('commentaire'));
            $nl = !empty($userComment) ? "\n" : '';
            $trimmedTypeDescription = !empty($trimmedTypeDescription) ? "\n" . $trimmedTypeDescription : '';
            $commentaire = $userComment . $nl . 'Type à la création -> ' . $typeLabel . $trimmedTypeDescription . "\n" . 'Statut à la création -> ' . $statutNom;
            if (!empty($commentaire)) {
                $histo = new LitigeHistoric();
                $histo
                    ->setDate(new DateTime('now'))
                    ->setComment($commentaire)
                    ->setLitige($litige)
                    ->setUser($this->getUser());
                $entityManager->persist($histo);
            }

            $this->persistAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);

            $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_ARRIVAGE);
            $arrivageResponse = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage'));
            $response = $arrivageResponse ? $arrivageResponse : [];

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-litige", name="litige_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deleteLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $litigeRepository = $entityManager->getRepository(Litige::class);
            $litige = $litigeRepository->find($data['litige']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($litige);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/ajouter-colis", name="arrivage_add_colis", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ColisService $colisService
     * @return JsonResponse|RedirectResponse
     * @throws NonUniqueResultException
     */
    public function addColis(Request $request,
                             EntityManagerInterface $entityManager,
                             ColisService $colisService)
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $natures = array_reduce(
                array_keys($data),
                function (array $carry, string $key) use ($data) {
                    $keyIntval = intval($key);
                    if (!empty($keyIntval)) {
                        $carry[$key] = $data[$key];
                    }
                    return $carry;
                },
                []
            );

            $persistedColis = $colisService->persistMultiColis($arrivage, $natures, $this->getUser());
            $entityManager->flush();

            return new JsonResponse([
                'colisIds' => array_map(function (Colis $colis) {
                    return $colis->getId();
                }, $persistedColis),
                'arrivageId' => $arrivage->getId(),
                'arrivage' => $arrivage->getNumeroArrivage()
            ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/litiges/api/{arrivage}", name="arrivageLitiges_api", options={"expose"=true}, methods="GET|POST")
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param Arrivage $arrivage
     * @return Response
     */
    public function apiArrivageLitiges(EntityManagerInterface $entityManager,
                                       Request $request,
                                       Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $litiges = $litigeRepository->findByArrivage($arrivage);
            $rows = [];
            foreach ($litiges as $litige) {
                $rows[] = [
                    'firstDate' => $litige->getCreationDate()->format('d/m/Y H:i'),
                    'status' => $litige->getStatus() ? $litige->getStatus()->getNom() : '',
                    'type' => $litige->getType() ? $litige->getType()->getLabel() : '',
                    'updateDate' => $litige->getUpdateDate() ? $litige->getUpdateDate()->format('d/m/Y H:i') : '',
                    'Actions' => $this->renderView('arrivage/datatableLitigesRow.html.twig', [
                        'arrivageId' => $arrivage->getId(),
                        'url' => [
                            'edit' => $this->generateUrl('litige_api_edit', ['id' => $litige->getId()])
                        ],
                        'litigeId' => $litige->getId(),
                    ]),
                    'urgence' => $litige->getEmergencyTriggered()
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier-litige", name="litige_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEditLitige(Request $request,
                                  UserService $userService,
                                  EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);
            $pieceJointeRepository = $entityManager->getRepository(PieceJointe::class);
            $usersRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($data['litigeId']);

            $colisCode = [];
            foreach ($litige->getColis() as $colis) {
                $colisCode[] = $colis->getId();

            }

            $arrivage = $arrivageRepository->find($data['arrivageId']);

            $hasRightToTreatLitige = $userService->hasRightFunction(Menu::QUALI, Action::TREAT_LITIGE);

            $html = $this->renderView('arrivage/modalEditLitigeContent.html.twig', [
                'litige' => $litige,
                'hasRightToTreatLitige' => $hasRightToTreatLitige,
                'utilisateurs' => $usersRepository->getIdAndLibelleBySearch(''),
                'typesLitige' => $typeRepository->findByCategoryLabel(CategoryType::LITIGE),
                'statusLitige' => $statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR, true),
                'attachements' => $pieceJointeRepository->findBy(['litige' => $litige]),
                'colis' => $arrivage->getColis(),
            ]);

            return new JsonResponse(['html' => $html, 'colis' => $colisCode]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-litige", name="litige_edit_arrivage",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param ArrivageDataService $arrivageDataService
     * @param EntityManagerInterface $entityManager
     * @param LitigeService $litigeService
     * @param Twig_Environment $templating
     * @return Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function editLitige(Request $request,
                               ArrivageDataService $arrivageDataService,
                               EntityManagerInterface $entityManager,
                               LitigeService $litigeService,
                               Twig_Environment $templating): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::QUALI, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $post = $request->request;

            $statutRepository = $entityManager->getRepository(Statut::class);
            $typeRepository = $entityManager->getRepository(Type::class);
            $colisRepository = $entityManager->getRepository(Colis::class);
            $litigeRepository = $entityManager->getRepository(Litige::class);
            $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);

            $litige = $litigeRepository->find($post->get('id'));
            $typeBefore = $litige->getType()->getId();
            $typeBeforeName = $litige->getType()->getLabel();
            $typeAfter = (int)$post->get('typeLitige');
            $statutBefore = $litige->getStatus()->getId();
            $statutBeforeName = $litige->getStatus()->getNom();
            $statutAfter = (int)$post->get('statutLitige');
            $litige
                ->setDeclarant($utilisateurRepository->find($post->get('declarantLitige')))
                ->setUpdateDate(new DateTime('now'));
            $this->templating = $templating;
            $newStatus = $statutRepository->find($statutAfter);
            $hasRightToTreatLitige = $this->userService->hasRightFunction(Menu::QUALI, Action::TREAT_LITIGE);
            if ($hasRightToTreatLitige || !$newStatus->getTreated()) {
                $litige->setStatus($newStatus);
            }

            if ($hasRightToTreatLitige) {
                $litige->setType($typeRepository->find($typeAfter));
            }

            if (!empty($newColis = $post->get('colis'))) {
                // on détache les colis existants...
                $existingColis = $litige->getColis();
                foreach ($existingColis as $existingColi) {
                    $litige->removeColis($existingColi);
                }
                // ... et on ajoute ceux sélectionnés
                $listColis = explode(',', $newColis);
                foreach ($listColis as $colisId) {
                    $litige->addColis($colisRepository->find($colisId));
                }
            }

            $entityManager->flush();

            $comment = '';
            $typeDescription = $litige->getType()->getDescription();
            if ($typeBefore !== $typeAfter) {
                $comment .= "Changement du type : "
                    . $typeBeforeName . " -> " . $litige->getType()->getLabel() . "." .
                    (!empty($typeDescription) ? ("\n" . $typeDescription . ".") : '');
            }
            if ($statutBefore !== $statutAfter) {
                if (!empty($comment)) {
                    $comment .= "\n";
                }
                $comment .= "Changement du statut : " .
                    $statutBeforeName . " -> " . $litige->getStatus()->getNom() . ".";
            }

            if ($post->get('commentaire')) {
                if (!empty($comment)) {
                    $comment .= "\n";
                }
                $comment .= trim($post->get('commentaire'));
            }

            if ($post->get('emergency')) {
                $litige->setEmergencyTriggered($post->get('emergency') === 'true');
            }

            if (!empty($comment)) {
                $histoLitige = new LitigeHistoric();
                $histoLitige
                    ->setLitige($litige)
                    ->setDate(new DateTime('now'))
                    ->setUser($this->getUser())
                    ->setComment($comment);
                $entityManager->persist($histoLitige);
                $entityManager->flush();
            }

            $listAttachmentIdToKeep = $post->get('files') ?? [];

            $attachments = $litige->getAttachements()->toArray();
            foreach ($attachments as $attachment) {
                /** @var PieceJointe $attachment */
                if (!in_array($attachment->getId(), $listAttachmentIdToKeep)) {
                    $this->attachmentService->removeAndDeleteAttachment($attachment, null, $litige);
                }
            }

            $this->persistAttachmentsForEntity($litige, $this->attachmentService, $request, $entityManager);
            $entityManager->flush();
            $isStatutChange = ($statutBefore !== $statutAfter);
            if ($isStatutChange) {
                $litigeService->sendMailToAcheteursOrDeclarant($litige, LitigeService::CATEGORY_ARRIVAGE, true);
            }

            $response = $this->getResponseReloadArrivage($entityManager, $arrivageDataService, $request->query->get('reloadArrivage'));

            return new JsonResponse($response);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/depose-pj-litige", name="litige_depose", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function deposeLitige(Request $request,
                                 EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            $fileNames = [];

            $litigeRepository = $entityManager->getRepository(Litige::class);
            $id = (int)$request->request->get('id');
            $litige = $litigeRepository->find($id);

            for ($i = 0; $i < count($request->files); $i++) {
                $file = $request->files->get('file' . $i);
                if ($file) {
                    $pj = $this->attachmentService->createPieceJointe($file, $litige);
                    $entityManager->persist($pj);

                    $fileNames[] = [
                        'name' => $pj->getFileName(),
                        'originalName' => $file->getClientOriginalName()
                    ];
                }
            }
            $entityManager->flush();

            $html = '';
            foreach ($fileNames as $fileName) {
                $html .= $this->renderView('attachment/attachmentLine.html.twig', [
                    'litige' => $litige,
                    'pjName' => $fileName['name'],
                    'originalName' => $fileName['originalName']
                ]);
            }

            return new JsonResponse($html);
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/colis/api/{arrivage}", name="colis_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param Arrivage $arrivage
     * @return Response
     */
    public function apiColis(Request $request,
                             EntityManagerInterface $entityManager,
                             Arrivage $arrivage): Response
    {
        if ($request->isXmlHttpRequest()) {
            $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);

            $listColis = $arrivage->getColis()->toArray();

            $rows = [];
            foreach ($listColis as $colis) {
                /** @var $colis Colis */
                $mouvement = $mouvementTracaRepository->getLastByColis($colis->getCode());
                $rows[] = [
                    'nature' => $colis->getNature() ? $colis->getNature()->getLabel() : '',
                    'code' => $colis->getCode(),
                    'lastMvtDate' => $mouvement ? ($mouvement->getDatetime() ? $mouvement->getDatetime()->format('d/m/Y H:i') : '') : '',
                    'lastLocation' => $mouvement ? ($mouvement->getEmplacement() ? $mouvement->getEmplacement()->getLabel() : '') : '',
                    'operator' => $mouvement ? ($mouvement->getOperateur() ? $mouvement->getOperateur()->getUsername() : '') : '',
                    'actions' => $this->renderView('arrivage/datatableColisRow.html.twig', [
                        'arrivageId' => $arrivage->getId(),
                        'colisId' => $colis->getId()
                    ])
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route(
     *     "/{arrivage}/colis/{colis}/etiquette",
     *     name="print_arrivage_single_colis_bar_codes",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     *
     * @param Arrivage $arrivage
     * @param Request $request
     * @param PDFGeneratorService $PDFGeneratorService
     * @param Colis|null $colis
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArrivageColisBarCodes(Arrivage $arrivage,
                                               Request $request,
                                               PDFGeneratorService $PDFGeneratorService,
                                               Colis $colis = null): Response
    {
        $barcodeConfigs = [];

        if (!isset($colis)) {
            $printColis = $request->query->getBoolean('printColis');
            $printArrivage = $request->query->getBoolean('printArrivage');

            if ($printColis) {
                $barcodeConfigs = $this->getBarcodeConfigPrintAllColis($arrivage);
            }

            if ($printArrivage) {
                $barcodeConfigs[] = [
                    'code' => $arrivage->getNumeroArrivage()
                ];
            }
        } else {
            if (!$colis->getArrivage() || $colis->getArrivage()->getId() !== $arrivage->getId()) {
                throw new NotFoundHttpException("404");
            }

            $barcodeConfigs[] = $this->getBarcodeColisConfig($colis, $arrivage->getDestinataire());
        }

        if (empty($barcodeConfigs)) {
            throw new BadRequestHttpException('Vous devez imprimer au moins une étiquette');
        }

        $fileName = $PDFGeneratorService->getBarcodeFileName($barcodeConfigs, 'arrivage');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barcodeConfigs),
            $fileName
        );
    }

    /**
     * @Route(
     *     "/{arrivage}/etiquettes",
     *     name="print_arrivage_bar_codes",
     *     options={"expose"=true},
     *     methods="GET"
     * )
     * @param Arrivage $arrivage
     * @param Request $request
     * @param PDFGeneratorService $PDFGeneratorService
     * @return Response
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printArrivageAlias(Arrivage $arrivage,
                                       Request $request,
                                       PDFGeneratorService $PDFGeneratorService)
    {
        return $this->printArrivageColisBarCodes($arrivage, $request, $PDFGeneratorService);
    }

    private function getBarcodeConfigPrintAllColis(Arrivage $arrivage)
    {
        return array_map(
            function (Colis $colisInArrivage) use ($arrivage) {
                return $this->getBarcodeColisConfig($colisInArrivage, $arrivage->getDestinataire());
            },
            $arrivage->getColis()->toArray()
        );
    }

    private function getBarcodeColisConfig(Colis $colis, ?Utilisateur $destinataire)
    {
        return [
            'code' => $colis->getCode(),
            'labels' => [
                $destinataire
                    ? $destinataire->getDropzone()
                    ? $destinataire->getDropzone()->getLabel()
                    : ''
                    : ''
            ]
        ];
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param ArrivageDataService $arrivageDataService
     * @param $reloadArrivageId
     * @return array|null
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function getResponseReloadArrivage(EntityManagerInterface $entityManager,
                                               ArrivageDataService $arrivageDataService,
                                               $reloadArrivageId): ?array
    {
        $response = null;
        if (isset($reloadArrivageId)) {
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            $arrivageToReload = $arrivageRepository->find($reloadArrivageId);
            if ($arrivageToReload) {
                $response = [
                    'entete' => $this->renderView('arrivage/arrivage-show-header.html.twig', [
                        'arrivage' => $arrivageToReload,
                        'canBeDeleted' => $arrivageRepository->countLitigesUnsolvedByArrivage($arrivageToReload) == 0,
                        'showDetails' => $arrivageDataService->createHeaderDetailsConfig($arrivageToReload)
                    ]),
                ];
            }
        }

        return $response;
    }

    /**
     * @param Arrivage|Litige $entity
     * @param AttachmentService $attachmentService
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     */
    private function persistAttachmentsForEntity($entity, AttachmentService $attachmentService, Request $request, EntityManagerInterface $entityManager) {
        $attachments = $attachmentService->createAttachements($request->files);
        foreach ($attachments as $attachment) {
            $entityManager->persist($attachment);
            $entity->addAttachement($attachment);
        }
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    /**
     * @Route("/colonne-visible", name="save_column_visible_for_arrivage", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function saveColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() ) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = json_decode($request->getContent(), true);

            $champs = array_keys($data);
            $user = $this->getUser();
            /** @var $user Utilisateur */
            $champs[] = "actions";
            $user->setColumnsVisibleForArrivage($champs);
            $entityManager->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/colonne-visible", name="get_column_visible_for_arrivage", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getColumnVisible(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_ARRI)) {
            return $this->redirectToRoute('access_denied');
        }
        $user = $this->getUser();     ;

        return new JsonResponse($user->getColumnsVisibleForArrivage());
    }
}
