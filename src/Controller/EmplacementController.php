<?php

namespace App\Controller;

use App\Entity\Dispatch;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Livraison;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Entity\TrackingMovement;
use App\Entity\Nature;
use App\Entity\ReferenceArticle;

use App\Service\GlobalParamService;
use App\Service\PDFGeneratorService;
use App\Service\UserService;
use App\Service\EmplacementDataService;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @Route("/emplacement")
 */
class EmplacementController extends AbstractController
{
    /**
     * @var EmplacementDataService
     */
    private $emplacementDataService;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var GlobalParamService
     */
    private $globalParamService;

    public function __construct(GlobalParamService $globalParamService,
                                EmplacementDataService $emplacementDataService,
                                UserService $userService)
    {
        $this->emplacementDataService = $emplacementDataService;
        $this->userService = $userService;
        $this->globalParamService = $globalParamService;
    }

    /**
     * @Route("/api", name="emplacement_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_EMPL)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->emplacementDataService->getEmplacementDataByParams($request->request);

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/", name="emplacement_index", methods="GET")
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function index(EntityManagerInterface $entityManager,
                          TranslatorInterface $translator): Response
    {
        if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_EMPL)) {
            return $this->redirectToRoute('access_denied');
        }

        $filtreSupRepository = $entityManager->getRepository(FiltreSup::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $allNatures = $natureRepository->findAll();

        $filterStatus = $filtreSupRepository->findOnebyFieldAndPageAndUser(FiltreSup::FIELD_STATUT, EmplacementDataService::PAGE_EMPLACEMENT, $this->getUser());
        $active = $filterStatus ? $filterStatus->getValue() : false;

		return $this->render('emplacement/index.html.twig', [
			'active' => $active,
            'natures' => $allNatures
		]);
    }

    /**
     * @Route("/creer", name="emplacement_new", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {

            $naturesRepository = $entityManager->getRepository(Nature::class);

            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }


            $errorResponse = $this->checkLocationLabel($data["Label"] ?? null);
            if ($errorResponse) {
                return $errorResponse;
            }

            $dateMaxTime = !empty($data['dateMaxTime']) ? $data['dateMaxTime'] : null;
            $errorResponse = $this->checkMaxTime($dateMaxTime);
            if ($errorResponse) {
                return $errorResponse;
            }


            $emplacement = new Emplacement();
            $emplacement
				->setLabel($data["Label"])
				->setDescription($data["Description"])
				->setIsActive(true)
                ->setDateMaxTime($dateMaxTime)
				->setIsDeliveryPoint($data["isDeliveryPoint"])
                ->setIsOngoingVisibleOnMobile($data["isDeliveryPoint"]);

            if (!empty($data['allowed-natures'])) {
                foreach ($data['allowed-natures'] as $allowedNatureId) {
                    $emplacement
                        ->addAllowedNature($naturesRepository->find($allowedNatureId));
                }
            }

            $entityManager->persist($emplacement);
            $entityManager->flush();
            return new JsonResponse(true);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-modifier", name="emplacement_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEdit(Request $request,
                            EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $natureRepository = $entityManager->getRepository(Nature::class);

            $allNatures = $natureRepository->findAll();
            $emplacement = $emplacementRepository->find($data['id']);
            $json = $this->renderView('emplacement/modalEditEmplacementContent.html.twig', [
                'emplacement' => $emplacement,
                'natures' => $allNatures
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/edit", name="emplacement_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $naturesRepository = $entityManager->getRepository(Nature::class);
            $errorResponse = $this->checkLocationLabel($data["Label"] ?? null, $data['id']);
            if ($errorResponse) {
                return $errorResponse;
            }

            $dateMaxTime = !empty($data['dateMaxTime']) ? $data['dateMaxTime'] : null;
            $errorResponse = $this->checkMaxTime($dateMaxTime);
            if ($errorResponse) {
                return $errorResponse;
            }

            $emplacement = $emplacementRepository->find($data['id']);
            $emplacement
                ->setLabel($data["Label"])
                ->setDescription($data["Description"])
            	->setIsDeliveryPoint($data["isDeliveryPoint"])
                ->setIsOngoingVisibleOnMobile($data["isOngoingVisibleOnMobile"])
                ->setDateMaxTime($dateMaxTime)
				->setIsActive($data['isActive']);

            $emplacement
                ->getAllowedNatures()->clear();


            if (!empty($data['allowed-natures'])) {
                foreach ($data['allowed-natures'] as $allowedNatureId) {
                    $emplacement
                        ->addAllowedNature($naturesRepository->find($allowedNatureId));
                }
            }

            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="emplacement_check_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @return Response
     */
    public function checkEmplacementCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $emplacementId = json_decode($request->getContent(), true)) {

            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DISPLAY_EMPL)) {
                return $this->redirectToRoute('access_denied');
            }

            $isUsedBy = $this->isEmplacementUsed($emplacementId);
            if (empty($isUsedBy)) {
                $delete = true;
                $html = $this->renderView('emplacement/modalDeleteEmplacementRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('emplacement/modalDeleteEmplacementWrong.html.twig', [
                    'delete' => false,
                    'isUsedBy' => $isUsedBy
                ]);
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @param int $emplacementId
     * @return array
     */
    private function isEmplacementUsed($emplacementId) {
        $entityManager = $this->getDoctrine()->getManager();
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);
        $articleRepository = $entityManager->getRepository(Article::class);
        $mouvementStockRepository = $entityManager->getRepository(MouvementStock::class);
        $trackingMovementRepository = $entityManager->getRepository(TrackingMovement::class);
        $collecteRepository = $entityManager->getRepository(Collecte::class);
        $livraisonRepository = $entityManager->getRepository(Livraison::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);

        $usedBy = [];

        $demandes = $demandeRepository->countByEmplacement($emplacementId);
        if ($demandes > 0) $usedBy[] = 'demandes';

        $dispatches = $dispatchRepository->countByEmplacement($emplacementId);
        if ($dispatches > 0) $usedBy[] = 'acheminements';

        $livraisons = $livraisonRepository->countByEmplacement($emplacementId);
        if ($livraisons > 0) $usedBy[] = 'livraisons';

        $collectes = $collecteRepository->countByEmplacement($emplacementId);
        if ($collectes > 0) $usedBy[] = 'collectes';

        $mouvementsStock = $mouvementStockRepository->countByEmplacement($emplacementId);
        if ($mouvementsStock > 0) $usedBy[] = 'mouvements de stock';

        $trackingMovements = $trackingMovementRepository->countByEmplacement($emplacementId);
        if ($trackingMovements > 0) $usedBy[] = 'mouvements de traçabilité';

        $refArticle = $referenceArticleRepository->countByEmplacement($emplacementId);
        if ($refArticle > 0)$usedBy[] = 'références article';

        $articles = $articleRepository->countByEmplacement($emplacementId);
        if ($articles > 0) $usedBy[] = 'articles';

        return $usedBy;
    }

    /**
     * @Route("/supprimer", name="emplacement_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::REFERENTIEL, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $response = [];

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);

            if ($emplacementId = (int)$data['emplacement']) {
                $emplacement = $emplacementRepository->find($emplacementId);

                if ($emplacement) {
					$usedEmplacement = $this->isEmplacementUsed($emplacementId);

					if (!empty($usedEmplacement)) {
						$emplacement->setIsActive(false);
					} else {
						$entityManager->remove($emplacement);
						$response['delete'] = $emplacementId;
					}
					$entityManager->flush();
				}
            }

            return new JsonResponse($response);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/autocomplete", name="get_emplacement", options={"expose"=true})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function getRefArticles(Request $request,
                                   EntityManagerInterface $entityManager)
    {
    	if ($request->isXmlHttpRequest()) {

            $search = $request->query->get('term');

            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $emplacement = $emplacementRepository->getIdAndLabelActiveBySearch($search);
            return new JsonResponse(['results' => $emplacement]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/etiquettes", name="print_locations_bar_codes", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param PDFGeneratorService $PDFGeneratorService
     * @return PdfResponse
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printLocationsBarCodes(Request $request,
                                           EntityManagerInterface $entityManager,
                                           PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $listEmplacements = explode(',', $request->query->get('listEmplacements') ?? '');
        $emplacementRepository = $entityManager->getRepository(Emplacement::class);

        if (!empty($listEmplacements)) {
            $barCodeConfigs = array_map(
                function (Emplacement $location) {
                    return ['code' => $location->getLabel()];
                },
                $emplacementRepository->findBy(['id' => $listEmplacements])
            );

            $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
                $fileName
            );
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }

    /**
     * @Route("/{location}/etiquette", name="print_single_location_bar_code", options={"expose"=true}, methods={"GET"})
     * @param Emplacement $location
     * @param PDFGeneratorService $PDFGeneratorService
     * @return PdfResponse
     * @throws LoaderError
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printSingleLocationBarCode(Emplacement $location,
                                               PDFGeneratorService $PDFGeneratorService): PdfResponse {
        $barCodeConfigs = [['code' => $location->getLabel()]];

        $fileName = $PDFGeneratorService->getBarcodeFileName($barCodeConfigs, 'emplacements');

        return new PdfResponse(
            $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodeConfigs),
            $fileName
        );
    }

    private function checkLocationLabel(?string $label, $locationId = null) {
        $labelTrimmed = $label ? trim($label) : null;
        if (!empty($labelTrimmed)) {
            $emplacementRepository = $this->getDoctrine()->getRepository(Emplacement::class);
            $emplacementAlreadyExist = $emplacementRepository->countByLabel($label, $locationId);
            if ($emplacementAlreadyExist) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Ce nom d'emplacement existe déjà. Veuillez en choisir un autre."
                ]);
            }
        }
        else {
            return new JsonResponse([
                'success' => false,
                'msg' => "Vous devez donner un nom valide."
            ]);
        }
        return null;
    }

    private function checkMaxTime(?string $dateMaxTime) {
        if (!empty($dateMaxTime)) {
            $matchHours = '\d+';
            $matchMinutes = '([0-5][0-9])';
            $matchHoursMinutes = "$matchHours:$matchMinutes";
            $resultFormat = preg_match(
                "/^$matchHoursMinutes$/",
                $dateMaxTime
            );
            if (empty($resultFormat)) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => "Le délai saisi est invalide."
                ]);
            }
        }
    }
}
