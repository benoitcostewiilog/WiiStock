<?php


namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Article;
use App\Entity\InventoryEntry;
use App\Entity\Menu;
use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
use WiiCommon\Helper\Stream;
use App\Service\CSVExportService;
use App\Service\InventoryEntryService;
use App\Service\InventoryService;
use App\Service\InvMissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use App\Service\UserService;

use DateTime;


/**
 * @Route("/inventaire/mission")
 */
class InventoryMissionController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InvMissionService
     */
    private $invMissionService;

    /**
     * @var InventoryService
     */
    private $inventoryService;

    public function __construct(UserService $userService,
                                InvMissionService $invMissionService,
                                InventoryService $inventoryService) {
        $this->userService = $userService;
        $this->invMissionService = $invMissionService;
        $this->inventoryService = $inventoryService;
    }

    /**
     * @Route("/", name="inventory_mission_index")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE})
     */
    public function index()
    {
        return $this->render('inventaire/index.html.twig');
    }

    /**
     * @Route("/api", name="inv_missions_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request,
                        InvMissionService $invMissionService): Response
    {
        $data = $invMissionService->getDataForMissionsDatatable($request->request);

        return new JsonResponse($data);
    }

    /**
     * @Route("/creer", name="mission_new", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function new(Request $request): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            if ($data['startDate'] > $data['endDate'])
                return new JsonResponse([
                    'success' => false,
                    'msg' => "La date de début doit être antérieure à celle de fin."
                ]);

            $em = $this->getDoctrine()->getManager();

            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate(DateTime::createFromFormat('Y-m-d', $data['startDate']))
                ->setEndPrevDate(DateTime::createFromFormat('Y-m-d', $data['endDate']));

            $em->persist($mission);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'La mission d\'inventaire a bien été créée.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/verification", name="mission_check_delete", options={"expose"=true}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function checkMissionCanBeDeleted(Request $request,
                                             EntityManagerInterface $entityManager): Response
    {
        if ($missionId = json_decode($request->getContent(), true)) {
            $inventoryEntryRepository = $entityManager->getRepository(InventoryEntry::class);
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);

            $missionArt = $inventoryMissionRepository->countArtByMission($missionId);
            $missionRef = $inventoryMissionRepository->countRefArtByMission($missionId);
            $missionEntries = $inventoryEntryRepository->countByMission($missionId);

            $missionIsUsed = (intval($missionArt) + intval($missionRef) + intval($missionEntries) > 0);

            if ($missionIsUsed) {
                $delete = false;
                $html = $this->renderView('inventaire/modalDeleteMissionWrong.html.twig');
            } else {
                $delete = true;
                $html = $this->renderView('inventaire/modalDeleteMissionRight.html.twig');
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer", name="mission_delete", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DELETE}, mode=HasPermission::IN_JSON)
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);
            $mission = $inventoryMissionRepository->find(intval($data['missionId']));

            $entityManager->remove($mission);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="inventory_mission_show", options={"expose"=true}, methods="GET|POST")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE})
     */
    public function show(InventoryMission $mission)
    {
        return $this->render('inventaire/show.html.twig', [
            'missionId' => $mission->getId()
        ]);
    }

    /**
     * @Route("/donnees_article/api/{id}", name="inv_entry_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function entryApiArticle(InventoryMission $mission,
                                    Request $request): Response
    {
        $data = $this->invMissionService->getDataForOneMissionDatatable($mission, $request->request, true);
        return new JsonResponse($data);
    }

    /**
     * @Route("/donnees_reference_article/api/{id}", name="inv_entry_reference_article_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function entryApiReferenceArticle(InventoryMission $mission,
                                             Request $request): Response
    {
        $data = $this->invMissionService->getDataForOneMissionDatatable($mission, $request->request, false);
        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter", name="add_to_mission", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::STOCK, Action::DISPLAY_INVE}, mode=HasPermission::IN_JSON)
     */
    public function addToMission(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $refArtRepository = $entityManager->getRepository(ReferenceArticle::class);
            $articleRepository = $entityManager->getRepository(Article::class);
            $inventoryMissionRepository = $entityManager->getRepository(InventoryMission::class);

            $mission = $inventoryMissionRepository->find($data['missionId']);
            $barcodeErrors = [];
            Stream::explode([",", " ", ";", "\t"], $data['articles'])
                ->filterMap(function($barcode) use ($articleRepository, $refArtRepository, $mission) {
                    $barcode = trim($barcode);

                    if($article = $articleRepository->findOneBy(["barCode" => $barcode])) {

                        $checkForArt = $article instanceof Article
                            && $article->getArticleFournisseur()->getReferenceArticle()->getStatut()->getNom() === ReferenceArticle::STATUT_ACTIF
                            && !$this->inventoryService->isInMissionInSamePeriod($article, $mission, false);

                        return $checkForArt ? $article : $article->getBarCode();
                    } else if($reference = $refArtRepository->findOneBy(["barCode" => $barcode])) {

                        $checkForRef = $reference instanceof ReferenceArticle
                            && $reference->getStatut()->getNom() === ReferenceArticle::STATUT_ACTIF
                            && !$this->inventoryService->isInMissionInSamePeriod($reference, $mission, true);

                        return $checkForRef ? $reference : $reference->getBarCode();
                    } else {
                        return $barcode;
                    }
                })
                ->each(function($refOrArt) use ($mission, &$barcodeErrors) {
                    if ($refOrArt instanceof ReferenceArticle || $refOrArt instanceof Article) {
                        $refOrArt->addInventoryMission($mission);
                    } else {
                        $barcodeErrors[] = $refOrArt;
                    }
                });

            $entityManager->flush();
            $success = count($barcodeErrors) === 0;
            $errorMsg = "";
            if (!$success) {
                $errorMsg = '<span class="pl-2">Les codes-barres suivants sont en erreur :</span><ul class="list-group my-2">';
                $errorMsg .= (
                    Stream::from($barcodeErrors)
                    ->map(function(string $barcode) {
                        return '<li class="list-group-item">' . $barcode . '</li>';
                    })
                    ->join("") . "</ul><span class='text-dark pl-2'>Les autres codes-barres ont bien été ajoutés à la mission.</span>"
                );
            }
            return new JsonResponse([
                'success' => $success,
                'msg' => $errorMsg
            ]);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/{mission}/csv", name="get_inventory_mission_csv", options={"expose"=true}, methods={"GET"})
     * @param InventoryEntryService $inventoryEntryService
     * @param CSVExportService $CSVExportService
     * @param InventoryMission $mission
     * @return Response
     */
    public function getInventoryMissionCSV(InventoryEntryService $inventoryEntryService,
                                           CSVExportService $CSVExportService,
                                           InventoryMission $mission): Response {

        $headers = [
            'Libellé',
            'Référence',
            'Code barre',
            'Quantité',
            'Emplacement',
            'Date dernier inventaire',
            'Anomalie'
        ];

        $missionStartDate = $mission->getStartPrevDate();
        $missionEndDate = $mission->getEndPrevDate();

        $inventoryEntries = Stream::from($mission->getEntries()->toArray())
            ->reduce(function (array $carry, InventoryEntry $entry) {
                $article = $entry->getArticle();
                $refArticle = $entry->getRefArticle();

                if (isset($article)) {
                    $barcode = $article->getBarCode();
                    $carry[$barcode] = $entry;
                }

                if (isset($refArticle)) {
                    $barcode = $refArticle->getBarCode();
                    $carry[$barcode] = $entry;
                }
                return $carry;
            }, []);

        $missionStartDateStr = $missionStartDate->format('d-m-Y');
        $missionEndDateStr = $missionEndDate->format('d-m-Y');

        return $CSVExportService->streamResponse(
            function ($output) use ($mission, $inventoryEntries, $CSVExportService, $inventoryEntryService, $missionStartDate, $missionEndDate) {
                $articles = $mission->getArticles();
                $refArticles = $mission->getRefArticles();
                /** @var Article $article */
                foreach ($articles as $article) {
                    $barcode = $article->getBarCode();
                    $inventoryEntryService->putMissionEntryLine($article, $inventoryEntries[$barcode] ?? null, $output);
                }

                /** @var ReferenceArticle $refArticle */
                foreach ($refArticles as $refArticle) {
                    $barcode = $refArticle->getBarCode();
                    $inventoryEntryService->putMissionEntryLine($refArticle, $inventoryEntries[$barcode] ?? null, $output);
                }
            },
            "Export_Mission_Inventaire_${missionStartDateStr}_${missionEndDateStr}.csv",
            [
                ['MISSION DU ' . $missionStartDate->format('d/m/Y') . ' AU ' . $missionEndDate->format('d/m/Y')],
                $headers
            ]
        );
    }
}
