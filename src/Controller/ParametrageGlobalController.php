<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\DaysWorked;
use App\Entity\DimensionsEtiquettes;
use App\Entity\Emplacement;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterRecord;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\Type;
use App\Entity\WorkFreeDay;
use App\Entity\ParametrageGlobal;
use App\Kernel;
use App\Service\AlertService;
use App\Service\AttachmentService;
use App\Service\GlobalParamService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage-global")
 */
class ParametrageGlobalController extends AbstractController
{

    private $engDayToFr = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    /**
     * @Route("/", name="global_param_index")
     */
    public function index(UserService $userService,
                          GlobalParamService $globalParamService,
                          EntityManagerInterface $entityManager,
                          SpecificService $specificService): Response {

        if(!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
            return $this->redirectToRoute('access_denied');
        }
        $statusRepository = $entityManager->getRepository(Statut::class);
        $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $dimensionsEtiquettesRepository = $entityManager->getRepository(DimensionsEtiquettes::class);
        $champsLibreRepository = $entityManager->getRepository(FreeField::class);
        $categoryCLRepository = $entityManager->getRepository(CategorieCL::class);
        $translationRepository = $entityManager->getRepository(Translation::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);
        $typeRepository = $entityManager->getRepository(Type::class);

        $labelLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::LABEL_LOGO);
        $emergencyIcon = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_ICON);
        $customIcon = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_ICON);
        $deliveryNoteLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DELIVERY_NOTE_LOGO);
        $waybillLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::WAYBILL_LOGO);

        $websiteLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::WEBSITE_LOGO);
        $emailLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMAIL_LOGO);
        $mobileLogoHeader = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO_HEADER);
        $mobileLogoLogin = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN);

        $clsForLabels = $champsLibreRepository->findBy([
            'categorieCL' => $categoryCLRepository->findOneByLabel(CategorieCL::ARTICLE)
        ]);
        $workFreeDays = array_map(
            function(WorkFreeDay $workFreeDay) {
                return $workFreeDay->getDay()->format('Y-m-d');
            },
            $workFreeDaysRepository->findAll()
        );

        return $this->render('parametrage_global/index.html.twig',
            [
                'logo' => ($labelLogo && file_exists(getcwd() . "/uploads/attachements/" . $labelLogo) ? $labelLogo : null),
                'emergencyIcon' => ($emergencyIcon && file_exists(getcwd() . "/uploads/attachements/" . $emergencyIcon) ? $emergencyIcon : null),
                'customIcon' => ($customIcon && file_exists(getcwd() . "/uploads/attachements/" . $customIcon) ? $customIcon : null),
                'titleEmergencyLabel' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL),
                'titleCustomLabel' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL),
                'dimensions_etiquettes' => $dimensionsEtiquettesRepository->findOneDimension(),
                'documentSettings' => [
                    'deliveryNoteLogo' => ($deliveryNoteLogo && file_exists(getcwd() . "/uploads/attachements/" . $deliveryNoteLogo) ? $deliveryNoteLogo : null),
                    'waybillLogo' => ($waybillLogo && file_exists(getcwd() . "/uploads/attachements/" . $waybillLogo) ? $waybillLogo : null),
                ],
                'receptionSettings' => [
                    'receptionLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION),
                    'listStatus' => $statusRepository->findByCategorieName(CategorieStatut::RECEPTION, 'displayOrder'),
                    'listStatusLitige' => $statusRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT)
                ],
                'deliverySettings' => [
                    'livraisonLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON),
                    'prepaAfterDl' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL),
                    'DLAfterRecep' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION),
                    'paramDemandeurLivraison' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEMANDEUR_DANS_DL),
                    'deliveryRequestTypes' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
                ],
                'arrivalSettings' => [
                    'redirect' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL) ?? true,
                    'listStatusLitige' => $statusRepository->findByCategorieName(CategorieStatut::LITIGE_ARR),
                    'defaultArrivalsLocation' => $globalParamService->getParamLocation(ParametrageGlobal::MVT_DEPOSE_DESTINATION),
                    'customsArrivalsLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DROP_OFF_LOCATION_IF_CUSTOMS),
                    'emergenciesArrivalsLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DROP_OFF_LOCATION_IF_EMERGENCY),
                    'emergencyTriggeringFields' => json_decode($parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS)),
                    'autoPrint' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::AUTO_PRINT_COLIS),
                    'sendMail' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL),
                    'printTwice' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PRINT_TWICE_CUSTOMS),
                ],
                'handlingSettings' => [
                    'removeHourInDatetime' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME),
                    'expectedDateColors' => [
                        'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_AFTER),
                        'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_D_DAY),
                        'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_BEFORE)
                    ]
                ],
                'stockSettings' => [
                    'alertThreshold' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_WARNING_THRESHOLD),
                    'securityThreshold' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_SECURITY_THRESHOLD),
                    'expirationDelay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY)
                ],
                'dispatchSettings' => [
                    'carrier' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CARRIER),
                    'consignor' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONSIGNER),
                    'receiver' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_RECEIVER),
                    'locationFrom' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_FROM),
                    'locationTo' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_TO),
                    'waybillContactName' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_NAME),
                    'waybillContactPhoneMail' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL),
                    'overconsumptionBill' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS),
                    'overconsumption_logo' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::OVERCONSUMPTION_LOGO),
                    'keepModal' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::KEEP_DISPATCH_PACK_MODAL_OPEN),
                    'openModal' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::OPEN_DISPATCH_ADD_PACK_MODAL_ON_CREATION),
                    'statuses' => $statusRepository->findByCategorieName(CategorieStatut::DISPATCH),
                    'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]),
                    'expectedDateColors' => [
                        'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
                        'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
                        'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
                    ]
                ],
                'mailerServer' => $mailerServerRepository->findOneMailerServer(),
                'wantsBL' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL),
                'wantsQTT' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_QTT_IN_LABEL),
                'blChosen' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CL_USED_IN_LABELS),
                'cls' => $clsForLabels,
                'translationSettings' => [
                    'translations' => $translationRepository->findAll(),
                    'menusTranslations' => array_column($translationRepository->getMenus(), '1')
                ],
                'paramCodeENC' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true,
                'encodings' => [ParametrageGlobal::ENCODAGE_EUW, ParametrageGlobal::ENCODAGE_UTF8],
                'paramCodeETQ' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128) ?? true,
                'typesETQ' => [ParametrageGlobal::CODE_128, ParametrageGlobal::QR_CODE],
                'fonts' => [ParametrageGlobal::FONT_MONTSERRAT, ParametrageGlobal::FONT_TAHOMA, ParametrageGlobal::FONT_MYRIAD],
                'fontFamily' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::FONT_FAMILY) ?? ParametrageGlobal::DEFAULT_FONT_FAMILY,
                'website_logo' => ($websiteLogo && file_exists(getcwd() . "/" . $websiteLogo) ? $websiteLogo : ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE),
                'email_logo' => ($emailLogo && file_exists(getcwd() . "/" . $emailLogo) ? $emailLogo : ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE),
                'mobile_logo_header' => ($mobileLogoHeader && file_exists(getcwd() . "/" . $mobileLogoHeader) ? $mobileLogoHeader : ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE),
                'mobile_logo_login' => ($mobileLogoLogin && file_exists(getcwd() . "/" . $mobileLogoLogin) ? $mobileLogoLogin : ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE),
                'redirectMvtTraca' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT),
                'workFreeDays' => $workFreeDays,
                'wantsRecipient' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL),
                'wantsDZLocation' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL),
                'wantsType' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL),
                'wantsCustoms' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL),
                'wantsEmergency' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL),
                'wantsCommandAndProjectNumber' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL),
                'wantsDestinationLocation' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL),
                'wantsRecipientArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL),
                'wantsDropzoneLocationArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL),
                'wantsBatchNumberArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL),
                'wantsExpirationDateArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL),
                'wantsPackCount' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL),
                'currentClient' => $specificService->getAppClient(),
                'isClientChangeAllowed' => $_SERVER["APP_ENV"] === "preprod"
            ]);
    }

    /**
     * @Route("/ajax-etiquettes", name="ajax_dimensions_etiquettes",  options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param UserService $userService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function ajaxDimensionEtiquetteServer(Request $request,
                                                 UserService $userService,
                                                 AttachmentService $attachmentService,
                                                 EntityManagerInterface $entityManager): Response {
        if(!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
            return $this->redirectToRoute('access_denied');
        }
        $data = $request->request->all();
        $dimensionsEtiquettesRepository = $entityManager->getRepository(DimensionsEtiquettes::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $dimensions = $dimensionsEtiquettesRepository->findOneDimension();
        if(!$dimensions) {
            $dimensions = new DimensionsEtiquettes();
            $entityManager->persist($dimensions);
        }
        $dimensions
            ->setHeight(intval($data['height']))
            ->setWidth(intval($data['width']));

        $parametrageGlobalQtt = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_QTT_IN_LABEL);

        if(empty($parametrageGlobalQtt)) {
            $parametrageGlobalQtt = new ParametrageGlobal();
            $parametrageGlobalQtt->setLabel(ParametrageGlobal::INCLUDE_QTT_IN_LABEL);
            $entityManager->persist($parametrageGlobalQtt);
        }
        $parametrageGlobalQtt
            ->setValue((int)($data['param-qtt-etiquette'] === 'true'));

        $parametrageGlobalRecipient = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL);

        if(empty($parametrageGlobalRecipient)) {
            $parametrageGlobalRecipient = new ParametrageGlobal();
            $parametrageGlobalRecipient->setLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL);
            $entityManager->persist($parametrageGlobalRecipient);
        }

        $parametrageGlobalRecipient
            ->setValue((int)($data['param-recipient-etiquette'] === 'true'));

        $parametrageGlobalDZLocation = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL);

        if(empty($parametrageGlobalDZLocation)) {
            $parametrageGlobalDZLocation = new ParametrageGlobal();
            $parametrageGlobalDZLocation->setLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL);
            $entityManager->persist($parametrageGlobalDZLocation);
        }

        $parametrageGlobalDZLocation
            ->setValue((int)($data['param-dz-location-etiquette'] === 'true'));

        $parametrageGlobalArrivalType = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL);

        if(empty($parametrageGlobalArrivalType)) {
            $parametrageGlobalArrivalType = new ParametrageGlobal();
            $parametrageGlobalArrivalType->setLabel(ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL);
            $entityManager->persist($parametrageGlobalArrivalType);
        }

        $parametrageGlobalArrivalType
            ->setValue((int)($data['param-type-arrival-etiquette'] === 'true'));

        $globalSettingsDestinationLocation = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);

        if(empty($globalSettingsDestinationLocation)) {
            $globalSettingsDestinationLocation = new ParametrageGlobal();
            $globalSettingsDestinationLocation->setLabel(ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsDestinationLocation);
        }

        $globalSettingsDestinationLocation
            ->setValue((int)($data['param-add-destination-location-article-label'] === 'true'));

        $globalSettingsRecipientOnArticleLabel = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);

        if(empty($globalSettingsRecipientOnArticleLabel)) {
            $globalSettingsRecipientOnArticleLabel = new ParametrageGlobal();
            $globalSettingsRecipientOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsRecipientOnArticleLabel);
        }

        $globalSettingsRecipientOnArticleLabel
            ->setValue((int)($data['param-add-recipient-article-label'] === 'true'));

        $globalSettingsRecipientDropzoneOnArticleLabel = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);

        if(empty($globalSettingsRecipientDropzoneOnArticleLabel)) {
            $globalSettingsRecipientDropzoneOnArticleLabel = new ParametrageGlobal();
            $globalSettingsRecipientDropzoneOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsRecipientDropzoneOnArticleLabel);
        }

        $globalSettingsRecipientDropzoneOnArticleLabel
            ->setValue((int)($data['param-add-recipient-dropzone-location-article-label'] === 'true'));

        $globalSettingsBatchNumberOnArticleLabel = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);

        if(empty($globalSettingsBatchNumberOnArticleLabel)) {
            $globalSettingsBatchNumberOnArticleLabel = new ParametrageGlobal();
            $globalSettingsBatchNumberOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsBatchNumberOnArticleLabel);
        }

        $globalSettingsBatchNumberOnArticleLabel
            ->setValue((int)($data['param-add-batch-number-article-label'] === 'true'));

        $globalSettingsExpirationDateOnArticleLabel = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);

        if(empty($globalSettingsExpirationDateOnArticleLabel)) {
            $globalSettingsExpirationDateOnArticleLabel = new ParametrageGlobal();
            $globalSettingsExpirationDateOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsExpirationDateOnArticleLabel);
        }

        $globalSettingsExpirationDateOnArticleLabel
            ->setValue((int)($data['param-add-expiration-date-article-label'] === 'true'));

        $parametrageGlobalCommandAndProjectNumbers = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);

        if(empty($parametrageGlobalCommandAndProjectNumbers)) {
            $parametrageGlobalCommandAndProjectNumbers = new ParametrageGlobal();
            $parametrageGlobalCommandAndProjectNumbers->setLabel(ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);
            $entityManager->persist($parametrageGlobalCommandAndProjectNumbers);
        }

        $parametrageGlobalCommandAndProjectNumbers
            ->setValue((int)($data['param-command-project-numbers-etiquette'] === 'true'));

        $parametrageGlobalPackCount = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL);

        if(empty($parametrageGlobalPackCount)) {
            $parametrageGlobalPackCount = new ParametrageGlobal();
            $parametrageGlobalPackCount->setLabel(ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL);
            $entityManager->persist($parametrageGlobalPackCount);
        }

        $parametrageGlobalPackCount->setValue((int)($data['param-pack-count'] === 'true'));

        $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);

        if(empty($parametrageGlobal)) {
            $parametrageGlobal = new ParametrageGlobal();
            $parametrageGlobal->setLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
            $entityManager->persist($parametrageGlobal);
        }
        $parametrageGlobal->setValue((int)($data['param-bl-etiquette'] === 'true'));

        $parametrageGlobal128 = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);

        if(empty($parametrageGlobal128)) {
            $parametrageGlobal128 = new ParametrageGlobal();
            $parametrageGlobal128->setLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
            $entityManager->persist($parametrageGlobal128);
        }
        $parametrageGlobal128->setValue($data['param-type-etiquette']);

        $parametrageGlobalCL = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CL_USED_IN_LABELS);

        if(empty($parametrageGlobalCL)) {
            $parametrageGlobalCL = new ParametrageGlobal();
            $parametrageGlobalCL->setLabel(ParametrageGlobal::CL_USED_IN_LABELS);
            $entityManager->persist($parametrageGlobalCL);
        }
        $parametrageGlobalCL->setValue($data['param-cl-etiquette']);

        $textEmergencyGlobalSettings = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL);

        if(empty($textEmergencyGlobalSettings)) {
            $textEmergencyGlobalSettings = new ParametrageGlobal();
            $textEmergencyGlobalSettings->setLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL);
            $entityManager->persist($textEmergencyGlobalSettings);
        }
        $textEmergencyGlobalSettings->setValue($data['emergency-title-label']);

        $textCustomGlobalSettings = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL);

        if(empty($textCustomGlobalSettings)) {
            $textCustomGlobalSettings = new ParametrageGlobal();
            $textCustomGlobalSettings->setLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL);
            $entityManager->persist($textCustomGlobalSettings);
        }
        $textCustomGlobalSettings->setValue($data['custom-title-label']);

        $includeEmergencyInLabelGlobalSettings = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL);

        if(empty($includeEmergencyInLabelGlobalSettings)) {
            $includeEmergencyInLabelGlobalSettings = new ParametrageGlobal();
            $includeEmergencyInLabelGlobalSettings->setLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL);
            $entityManager->persist($includeEmergencyInLabelGlobalSettings);
        }
        $includeEmergencyInLabelGlobalSettings->setValue((int)($data['param-emergency-etiquette'] === 'true'));

        $includeCustomInLabelGlobalSettings = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL);

        if(empty($includeCustomInLabelGlobalSettings)) {
            $includeCustomInLabelGlobalSettings = new ParametrageGlobal();
            $includeCustomInLabelGlobalSettings->setLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL);
            $entityManager->persist($includeCustomInLabelGlobalSettings);
        }
        $includeCustomInLabelGlobalSettings->setValue((int)($data['param-custom-etiquette'] === 'true'));

        $parametrageGlobalLogo = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::LABEL_LOGO);

        if(!empty($request->files->all()['logo'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['logo'], AttachmentService::LABEL_LOGO);
            if(empty($parametrageGlobalLogo)) {
                $parametrageGlobalLogo = new ParametrageGlobal();
                $parametrageGlobalLogo
                    ->setLabel(ParametrageGlobal::LABEL_LOGO);
                $entityManager->persist($parametrageGlobalLogo);
            }
            $parametrageGlobalLogo->setValue($fileName[array_key_first($fileName)]);
        }

        $customIconGlobalSettings = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::CUSTOM_ICON);

        if(!empty($request->files->all()['custom-icon'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['custom-icon'], AttachmentService::CUSTOM_ICON);
            if(empty($customIconGlobalSettings)) {
                $customIconGlobalSettings = new ParametrageGlobal();
                $customIconGlobalSettings
                    ->setLabel(ParametrageGlobal::CUSTOM_ICON);
                $entityManager->persist($customIconGlobalSettings);
            }
            $customIconGlobalSettings->setValue($fileName[array_key_first($fileName)]);
        }

        $emergencyIconGlobalSettings = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::EMERGENCY_ICON);

        if(!empty($request->files->all()['emergency-icon'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['emergency-icon'], AttachmentService::EMERGENCY_ICON);
            if(empty($emergencyIconGlobalSettings)) {
                $emergencyIconGlobalSettings = new ParametrageGlobal();
                $emergencyIconGlobalSettings
                    ->setLabel(ParametrageGlobal::EMERGENCY_ICON);
                $entityManager->persist($emergencyIconGlobalSettings);
            }
            $emergencyIconGlobalSettings->setValue($fileName[array_key_first($fileName)]);
        }
        $entityManager->flush();

        return new JsonResponse($data);
    }

    /**
     * @Route("/ajax-documents", name="ajax_documents",  options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param UserService $userService
     * @param AttachmentService $attachmentService
     * @param EntityManagerInterface $em
     * @return Response
     * @throws NonUniqueResultException
     */
    public function ajaxDocuments(Request $request,
                                  UserService $userService,
                                  AttachmentService $attachmentService,
                                  EntityManagerInterface $em): Response {
        if(!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
            return $this->redirectToRoute('access_denied');
        }

        $pgr = $em->getRepository(ParametrageGlobal::class);

        if($request->files->has("logo-delivery-note")) {
            $logo = $request->files->get("logo-delivery-note");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::DELIVERY_NOTE_LOGO);
            $setting = $pgr->findOneByLabel(ParametrageGlobal::DELIVERY_NOTE_LOGO);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::DELIVERY_NOTE_LOGO);
                $em->persist($setting);
            }

            $setting->setValue($fileName[array_key_first($fileName)]);
        }

        if($request->files->has("logo-waybill")) {
            $logo = $request->files->get("logo-waybill");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::WAYBILL_LOGO);
            $setting = $pgr->findOneByLabel(ParametrageGlobal::WAYBILL_LOGO);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::WAYBILL_LOGO);
                $em->persist($setting);
            }

            $setting->setValue($fileName[array_key_first($fileName)]);
        }

        $em->flush();

        return $this->json([]);
    }

    /**
     * @Route("/ajax-update-prefix-demand", name="ajax_update_prefix_demand",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function updatePrefixDemand(Request $request,
                                       EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeNomDemandeRepository = $entityManager->getRepository(PrefixeNomDemande::class);
            $prefixeDemande = $prefixeNomDemandeRepository->findOneByTypeDemande($data['typeDemande']);

            if($prefixeDemande == null) {
                $newPrefixe = new PrefixeNomDemande();
                $newPrefixe
                    ->setTypeDemandeAssociee($data['typeDemande'])
                    ->setPrefixe($data['prefixe']);

                $entityManager->persist($newPrefixe);
            } else {
                $prefixeDemande->setPrefixe($data['prefixe']);
            }
            $entityManager->flush();
            return new JsonResponse(['typeDemande' => $data['typeDemande'], 'prefixe' => $data['prefixe']]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax-update-expiration-delay", name="ajax_update_expiration_delay",  options={"expose"=true},  methods="POST")
     */
    public function updateExpirationDelay(Request $request,
                                          EntityManagerInterface $manager,
                                          AlertService $service) {
        $expirationDelay = $request->request->get('expirationDelay');

        $parametrageGlobalRepository = $manager->getRepository(ParametrageGlobal::class);
        $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY);

        if(empty($setting)) {
            $setting = new ParametrageGlobal();
            $setting->setLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY);
            $manager->persist($setting);
        }

        $setting->setValue($expirationDelay);
        $manager->flush();

        $service->generateAlerts($manager);

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Route("/ajax-get-prefix-demand", name="ajax_get_prefix_demand",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getPrefixDemand(Request $request, EntityManagerInterface $entityManager) {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $prefixeNomDemandeRepository = $entityManager->getRepository(PrefixeNomDemande::class);
            $prefixeNomDemande = $prefixeNomDemandeRepository->findOneByTypeDemande($data);

            $prefix = $prefixeNomDemande ? $prefixeNomDemande->getPrefixe() : '';

            return new JsonResponse($prefix);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="days_param_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function api(Request $request,
                        UserService $userService,
                        EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest()) {
            if(!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
                return $this->redirectToRoute('access_denied');
            }

            $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);

            $days = $daysWorkedRepository->findAllOrdered();
            $rows = [];
            foreach($days as $day) {
                $url['edit'] = $this->generateUrl('days_api_edit', ['id' => $day->getId()]);

                $rows[] =
                    [
                        'Day' => $this->engDayToFr[$day->getDay()],
                        'Worked' => $day->getWorked() ? 'oui' : 'non',
                        'Times' => $day->getTimes() ?? '',
                        'Order' => $day->getDisplayOrder(),
                        'Actions' => $this->renderView('parametrage_global/datatableDaysRow.html.twig', [
                            'url' => $url,
                            'dayId' => $day->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="days_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEdit(Request $request,
                            UserService $userService,
                            EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if(!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);

            $day = $daysWorkedRepository->find($data['id']);

            $json = $this->renderView('parametrage_global/modalEditDaysContent.html.twig', [
                'day' => $day,
                'dayWeek' => $this->engDayToFr[$day->getDay()]
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="days_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request,
                         UserService $userService,
                         EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if(!$userService->hasRightFunction(Menu::PARAM, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);

            $day = $daysWorkedRepository->find($data['day']);
            $dayName = $day->getDay();

            $day->setWorked($data['worked']);

            if(isset($data['times'])) {
                if($day->getWorked()) {
                    $matchHours = '((0[0-9])|(1[0-9])|(2[0-3]))';
                    $matchMinutes = '([0-5][0-9])';
                    $matchHoursMinutes = "$matchHours:$matchMinutes";
                    $matchPeriod = "$matchHoursMinutes-$matchHoursMinutes";
                    // return 0 if it's not match or false if error
                    $resultFormat = preg_match(
                        "/^($matchPeriod(;$matchPeriod)*)?$/",
                        $data['times']
                    );

                    if(!$resultFormat) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => 'Le format des horaires est incorrect.'
                        ]);
                    }

                    $day->setTimes($data['times']);
                } else {
                    $day->setTimes(null);
                }
            }

            $entityManager->persist($day);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le jour "' . $this->engDayToFr[$dayName] . '" a bien été modifié.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax-mail-server", name="ajax_mailer_server",  options={"expose"=true},  methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function ajaxMailerServer(Request $request,
                                     UserService $userService,
                                     EntityManagerInterface $entityManager): Response {
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if(!$userService->hasRightFunction(Menu::PARAM, Action::DISPLAY_GLOB)) {
                return $this->redirectToRoute('access_denied');
            }

            $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
            $mailerServer = $mailerServerRepository->findOneMailerServer();
            if(!$mailerServer) {
                $mailerServer = new MailerServer();
                $entityManager->persist($mailerServer);
            }

            $mailerServer
                ->setUser($data['user'])
                ->setPassword($data['password'])
                ->setPort($data['port'])
                ->setProtocol($data['protocol'] ?? null)
                ->setSmtp($data['smtp'])
                ->setSenderName($data['senderName'])
                ->setSenderMail($data['senderMail']);

            $entityManager->flush();

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/changer-parametres", name="toggle_params", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function toggleParams(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $ifExist = $parametrageGlobalRepository->findOneByLabel($data['param']);
            $em = $this->getDoctrine()->getManager();
            if($ifExist) {
                $ifExist->setValue($data['val']);
                $em->flush();
            } else {
                $parametrage = new ParametrageGlobal();
                $parametrage
                    ->setLabel($data['param'])
                    ->setValue($data['val']);
                $em->persist($parametrage);
                $em->flush();
            }
            return new JsonResponse(true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/personnalisation", name="save_translations", options={"expose"=true}, methods="POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param TranslationService $translationService
     * @return Response
     * @throws \Exception
     */
    public function saveTranslations(Request $request,
                                     EntityManagerInterface $entityManager,
                                     TranslationService $translationService): Response {
        if($request->isXmlHttpRequest() && $translations = json_decode($request->getContent(), true)) {
            $translationRepository = $entityManager->getRepository(Translation::class);
            foreach($translations as $translation) {
                $translationObject = $translationRepository->find($translation['id']);
                if($translationObject) {
                    $translationObject
                        ->setTranslation($translation['val'] ?: null)
                        ->setUpdated(1);
                } else {
                    return new JsonResponse(false);
                }
            }
            $entityManager->flush();

            $translationService->generateTranslationsFile();
            $translationService->cacheClearWarmUp();

            return new JsonResponse(true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/statuts-receptions",
     *     name="edit_status_receptions",
     *     options={"expose"=true},
     *     methods="POST",
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editStatusReceptions(Request $request,
                                         EntityManagerInterface $entityManager): Response {
        $statusRepository = $entityManager->getRepository(Statut::class);

        $statusCodes = $request->request->all();

        foreach($statusCodes as $statusId => $statusName) {
            $status = $statusRepository->find($statusId);

            if($status) {
                $status->setNom($statusName);
            }
        }
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse(true);
    }

    /**
     * @Route("/personnalisation-encodage", name="save_encodage", options={"expose"=true}, methods="POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function saveEncodage(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8);
            $em = $this->getDoctrine()->getManager();
            if(empty($parametrageGlobal)) {
                $parametrageGlobal = new ParametrageGlobal();
                $parametrageGlobal->setLabel(ParametrageGlobal::USES_UTF8);
                $em->persist($parametrageGlobal);
            }
            $parametrageGlobal->setValue($data);

            $em->flush();

            return new JsonResponse(true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/appearance", name="edit_appearance", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param AttachmentService $attachmentService
     * @param GlobalParamService $globalParamService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editAppearance(Request $request,
                                   EntityManagerInterface $entityManager,
                                   AttachmentService $attachmentService,
                                   GlobalParamService $globalParamService): Response {

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $resetLogos = json_decode($request->request->get('reset-logos', '[]'), true);

        if($request->files->has("website-logo")) {
            $logo = $request->files->get("website-logo");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::WEBSITE_LOGO);
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::WEBSITE_LOGO);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::WEBSITE_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("website-logo")) && ($resetLogos['website'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::WEBSITE_LOGO);
            $setting->setValue(ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE);
        }

        if($request->files->has("email-logo")) {
            $logo = $request->files->get("email-logo");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::EMAIL_LOGO);
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::EMAIL_LOGO);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::EMAIL_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("email-logo")) && ($resetLogos['mailLogo'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::EMAIL_LOGO);
            $setting->setValue(ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE);
        }

        if($request->files->has("mobile-logo-login")) {
            $logo = $request->files->get("mobile-logo-login");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::MOBILE_LOGO_LOGIN);
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("mobile-logo-login")) && ($resetLogos['nomadeAccueil'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN);
            $setting->setValue(ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE);
        }

        if($request->files->has("mobile-logo-header")) {
            $logo = $request->files->get("mobile-logo-header");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::MOBILE_LOGO_HEADER);
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::MOBILE_LOGO_HEADER);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::MOBILE_LOGO_HEADER);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("mobile-logo-header")) && ($resetLogos['nomadeHeader'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::MOBILE_LOGO_HEADER);
            $setting->setValue(ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE);
        }

        if($request->request->has("font-family")) {
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::FONT_FAMILY);
            if(!$parametrageGlobal) {
                $parametrageGlobal = new ParametrageGlobal();
                $parametrageGlobal->setLabel(ParametrageGlobal::FONT_FAMILY);
                $entityManager->persist($parametrageGlobal);
            } else {
                $originalValue = $parametrageGlobal->getValue();
            }

            $parametrageGlobal->setValue($request->request->get("font-family"));

            if(isset($originalValue) && $originalValue != $request->request->get("font-family")) {
                $globalParamService->generateScssFile($parametrageGlobal);

                $env = $_SERVER["APP_ENV"] == "dev" ? "dev" : "production";
                $process = Process::fromShellCommandline("yarn build:only:$env");
                $process->run();
            }
        }

        $entityManager->flush();

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/dispatch/overconsumption", name="edit_overconsumption_logo", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param AttachmentService $attachmentService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editOverconsumptionLogo(Request $request,
                                            EntityManagerInterface $entityManager,
                                            AttachmentService $attachmentService): Response {

        if($request->files->has("overconsumption-logo")) {
            $logo = $request->files->get("overconsumption-logo");

            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $fileName = $attachmentService->saveFile($logo, AttachmentService::OVERCONSUMPTION_LOGO);
            $setting = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::OVERCONSUMPTION_LOGO);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::OVERCONSUMPTION_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        }

        $entityManager->flush();

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/obtenir-encodage", name="get_encodage", options={"expose"=true}, methods="POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function getEncodage(Request $request,
                                EntityManagerInterface $entityManager): Response {
        if($request->isXmlHttpRequest()) {
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $parametrageGlobal = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::USES_UTF8);
            return new JsonResponse($parametrageGlobal ? $parametrageGlobal->getValue() : true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/obtenir-type-code", name="get_is_code_128", options={"expose"=true}, methods="POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     * @throws NonUniqueResultException
     */
    public function getIsCode128(Request $request,
                                 EntityManagerInterface $entityManager) {
        if($request->isXmlHttpRequest()) {
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $parametrageGlobal128 = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
            return new JsonResponse($parametrageGlobal128 ? $parametrageGlobal128->getValue() : true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/edit-param-locations/{label}",
     *     name="edit_param_location",
     *     options={"expose"=true},
     *     methods="GET|POST",
     *     condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param string $label
     * @return Response
     * @throws NonUniqueResultException
     */
    public function editParamLocation(Request $request,
                                      EntityManagerInterface $entityManager,
                                      string $label): Response {
        $value = json_decode($request->getContent(), true);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $parametrage = $parametrageGlobalRepository->findOneByLabel($label);
        $em = $this->getDoctrine()->getManager();
        if(!$parametrage) {
            $parametrage = new ParametrageGlobal();
            $parametrage->setLabel($label);
            $em->persist($parametrage);
        }
        $parametrage->setValue($value);

        $em->flush();
        return new JsonResponse(true);
    }

    private function setLocationListCluster(?string $clusterCode,
                                            ?array $listLocationIds,
                                            EntityManagerInterface $entityManager) {
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $locationClusterRepository = $entityManager->getRepository(LocationCluster::class);

        $cluster = $locationClusterRepository->findOneBy(['code' => $clusterCode]);

        if(!$cluster) {
            $cluster = new LocationCluster();
            $cluster->setCode($clusterCode);
            $entityManager->persist($cluster);
        }

        /** @var Emplacement $locationInCluster */
        foreach($cluster->getLocations() as $locationInCluster) {
            $locationId = (string)$locationInCluster->getId();
            // check if location is removed from cluster
            if(empty($listLocationIds)
                || !in_array($locationId, $listLocationIds)) {

                $records = $cluster->getLocationClusterRecords(true);
                /** @var LocationClusterRecord $record */
                foreach($records as $record) {
                    $recordLastTracking = $record->getLastTracking();
                    $recordFirstDrop = $record->getFirstDrop();
                    $lastTrackingIsOnLocation = (
                        $recordLastTracking
                        && $recordLastTracking->getEmplacement() === $locationInCluster
                    );
                    $firstDropIsOnLocation = (
                        $recordFirstDrop
                        && $recordFirstDrop->getEmplacement() === $locationInCluster
                    );
                    if($lastTrackingIsOnLocation
                        || (
                            $firstDropIsOnLocation
                            && ($recordFirstDrop === $recordLastTracking)
                        )) {
                        $entityManager->remove($record);
                    } else if((
                        $firstDropIsOnLocation
                        && ($recordFirstDrop !== $recordLastTracking)
                    )) {
                        $pack = $recordFirstDrop->getPack();
                        $trackingMovements = $pack->getTrackingMovements();
                        $newFirstDrop = null;
                        foreach($trackingMovements as $trackingMovement) {
                            if($trackingMovement === $recordFirstDrop) {
                                break;
                            } else if($trackingMovement->isDrop()) {
                                $newFirstDrop = $trackingMovement;
                            }
                        }
                        if(isset($newFirstDrop)) {
                            $record->setFirstDrop($newFirstDrop);
                        } else {
                            $entityManager->remove($record);
                        }
                    }
                }

                $locationInCluster->removeCluster($cluster);
            }
        }

        if(!empty($listLocationIds)) {
            foreach($listLocationIds as $locationId) {
                $location = $locationRepository->find($locationId);
                $location->addCluster($cluster);
            }
        }
    }

    /**
     * @Route("/modifier-client", name="toggle_app_client", options={"expose"=true}, methods="GET|POST")
     */
    public function toggleAppClient(Request $request): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $configPath = "/etc/php7/php-fpm.conf";

            //if we're not on a kubernetes pod => file doesn't exist => ignore
            if(!file_exists($configPath)) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le client ne peut pas être modifié sur cette instance",
                ]);
            }

            try {
                $config = file_get_contents($configPath);
                $newAppClient = "env[APP_CLIENT] = $data";

                $config = preg_replace("/^env\[APP_CLIENT\] = .*$/mi", $newAppClient, $config);
                file_put_contents($configPath, $config);

                //magie noire qui recharge la config php fpm sur les pods kubernetes :
                //pgrep recherche l'id du processus de php fpm
                //kill envoie un message USER2 (qui veut dire "recharge la configuration") à phpfpm
                exec("kill -USR2 $(pgrep -o php-fpm7)");

                return $this->json([
                    "success" => true,
                    "msg" => "Le client de l'application a bien été modifié",
                ]);
            } catch (Exception $exception) {
                return $this->json([
                    "success" => false,
                    "msg" => "Une erreur est survenue lors du changement du client",
                ]);
            }
        }

        throw new BadRequestHttpException();
    }
}
