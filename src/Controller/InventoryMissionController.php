<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\InventoryMission;

use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    public function __construct(InventoryMissionRepository $inventoryMissionRepository, UserService $userService, InventoryEntryRepository $inventoryEntryRepository, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
    }

    /**
     * @Route("/", name="inventory_mission_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('inventaire/index.html.twig');
    }

    /**
     * @Route("/api", name="inv_missions_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $missions = $this->inventoryMissionRepository->findAll();

            $rows = [];
            foreach ($missions as $mission) {
                $anomaly = $this->inventoryMissionRepository->countByMissionAnomaly($mission);

                $nbArtInMission = $this->articleRepository->countByMission($mission);
                $nbRefInMission = $this->referenceArticleRepository->countByMission($mission);
                $nbEntriesInMission = $this->inventoryEntryRepository->countByMission($mission);

                $rateBar = ($nbArtInMission + $nbRefInMission) != 0 ? $nbEntriesInMission * 100 / ($nbArtInMission + $nbRefInMission) : 0;

                $rows[] =
                    [
                        'StartDate' => $mission->getStartPrevDate()->format('d/m/Y'),
                        'EndDate' => $mission->getEndPrevDate()->format('d/m/Y'),
                        'Anomaly' => $anomaly != 0,
                        'Rate' => $this->renderView('inventaire/datatableMissionsBar.html.twig', [
                            'rateBar' => $rateBar
                        ]),
                        'Actions' => $this->renderView('inventaire/datatableMissionsRow.html.twig', [
                            'missionId' => $mission->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete/ref", name="get_refArticles", options={"expose"=true})
     */
    public function getRefArticlesSelect(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $refArticles = $this->referenceArticleRepository->getIdAndReferenceBySearch($search);
            return new JsonResponse(['results' => $refArticles]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/autocomplete", name="get_articles", options={"expose"=true})
     */
    public function getArticlesSelect(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $articles = $this->articleRepository->getIdAndReferenceBySearch($search);
            return new JsonResponse(['results' => $articles]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="mission_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            $mission = new InventoryMission();
            $mission
                ->setStartPrevDate(DateTime::createFromFormat('Y-m-d', $data['startDate']))
                ->setEndPrevDate(DateTime::createFromFormat('Y-m-d', $data['endDate']));

            $em->persist($mission);
            $em->flush();

            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/verification", name="mission_check_delete", options={"expose"=true})
     */
    public function checkUserCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $missionId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $missionArt = $this->inventoryMissionRepository->countArtByMission($missionId);
            $missionRef = $this->inventoryMissionRepository->countRefArtByMission($missionId);

            if ($missionArt != 0 || $missionRef != 0)
                $missionIsUsed = false;
            else
                $missionIsUsed = true;

            if ($missionIsUsed == true) {
                $delete = true;
                $html = $this->renderView('inventaire/modalDeleteMissionRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('inventaire/modalDeleteMissionWrong.html.twig');
            }
            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="mission_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $mission = $this->inventoryMissionRepository->find($data['missionId']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($mission);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir/{id}", name="inventory_mission_show", options={"expose"=true}, methods="GET|POST")
     */
    public function show(InventoryMission $mission)
    {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $articles = $this->articleRepository->findAll();
            $refArticles = $this->referenceArticleRepository->findAll();
            return $this->render('inventaire/show.html.twig', [
                'missionId' => $mission->getId(),
                'articles' => $articles,
                'refArticles' => $refArticles,
            ]);
    }

    /**
     * @Route("/donnees/api/{id}", name="inv_entry_api", options={"expose"=true}, methods="GET|POST")
     */
    public function entryApi(InventoryMission $mission)
    {
        if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $refArray = $this->referenceArticleRepository->getByMission($mission);
        $artArray = $this->articleRepository->getByMission($mission);

        $rows = [];
        foreach ($refArray as $ref) {
            // TODO HM
            $refDate = $this->referenceArticleRepository->getEntryDateByMission($mission, $ref['id']);
            if ($refDate != null)
               $refDate = $refDate['date']->format('d/m/Y');
            $rows[] =
                [
                    'Label' => $ref['libelle'],
                    'Ref' => $ref['reference'],
                    'Date' => $refDate,
                    'Anomaly' => $ref['hasInventoryAnomaly'] ? 'oui' : 'non'
                ];
        }
        foreach ($artArray as $article) {
            $artDate = $this->articleRepository->getEntryDateByMission($mission, $article['id']);
            if ($artDate != null) {
                $artDate = $artDate['date']->format('d/m/Y');
            }
            $rows[] =
                [
                    'Label' => $article['label'],
                    'Ref' => $article['reference'],
                    'Date' => $artDate,
                    'Anomaly' => $article['hasInventoryAnomaly'] ? 'oui' : 'non'
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/ajouter", name="add_to_mission", options={"expose"=true}, methods="GET|POST")
     */
    public function addToMission(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

            $mission = $this->inventoryMissionRepository->find($data['missionId']);

            foreach ($data['articles'] as $articleId) {
                $article = $this->articleRepository->find($articleId);
                $article->addInventoryMission($mission);
                $em->persist($mission);
                $em->flush();
            }

            foreach ($data['refArticles'] as $refArticleId) {
                $refArticle = $this->referenceArticleRepository->find($refArticleId);
                $refArticle->addInventoryMission($mission);
                $em->persist($mission);
                $em->flush();
            }

            return new JsonResponse();
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }


    /**
     * @Route("/mission-infos", name="get_mission_for_csv", options={"expose"=true}, methods={"GET","POST"})
     */
    public function getMouvementIntels(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
        	$mission = $this->inventoryMissionRepository->find($data['missionId']);

            $articles = $mission->getArticles();
            $refArticles = $mission->getRefArticles();
            $missionStartDate = $mission->getStartPrevDate();
            $missionEndDate = $mission->getEndPrevDate();

            $missionHeader = ['MISSION DU ' . $missionStartDate->format('d/m/Y') . ' AU ' . $missionEndDate->format('d/m/Y')];
            $headers = ['référence', 'label', 'quantité', 'emplacement'];

            $data = [];
            $data[] = $missionHeader;
            $data[] = $headers;

            foreach ($articles as $article) {
                $articleData = [];

                $articleData[] = $article->getReference();
                $articleData[] = $article->getLabel();
                $articleData[] = $article->getQuantite();
                $articleData[] = $article->getEmplacement()->getLabel();

                $data[] = $articleData;
            }

            foreach ($refArticles as $refArticle) {
                $refArticleData = [];

                $refArticleData[] = $refArticle->getReference();
                $refArticleData[] = $refArticle->getLibelle();
                $refArticleData[] = $refArticle->getQuantiteStock();
                $refArticleData[] = $refArticle->getEmplacement()->getLabel();

                $data[] = $refArticleData;
            }

            return new JsonResponse($data);
        } else {
            throw new NotFoundHttpException('404');
        }
    }
}