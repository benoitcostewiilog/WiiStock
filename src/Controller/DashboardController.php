<?php

namespace App\Controller;

use App\Entity\Dashboard\ComponentType;
use App\Entity\Utilisateur;
use App\Service\DashboardService;
use App\Service\DashboardSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/")
 */
class DashboardController extends AbstractController {

    /**
     * @Route("/accueil", name="accueil")
     * @param DashboardService $dashboardService
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @return Response
     */
    public function dashboards(DashboardService $dashboardService,
                               DashboardSettingsService $dashboardSettingsService,
                               EntityManagerInterface $manager): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser, DashboardSettingsService::MODE_DISPLAY),
            "refreshed" => $dashboardService->refreshDate($manager),
        ]);
    }

    /**
     * @Route("/dashboard/{token}", name="dashboards_external", options={"expose"=true})
     * @param DashboardService $dashboardService
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @param string $token
     * @return Response
     */
    public function external(DashboardService $dashboardService,
                             DashboardSettingsService $dashboardSettingsService,
                             EntityManagerInterface $manager,
                             string $token): Response {
        if ($token != $_SERVER["APP_DASHBOARD_TOKEN"]) {
            return $this->redirectToRoute("access_denied");
        }

        return $this->render("dashboard/external.html.twig", [
            "title" => "Dashboard externe", //ne s'affiche normalement jamais
            "dashboards" => $dashboardSettingsService->serialize($manager, null, DashboardSettingsService::MODE_EXTERNAL),
            "refreshed" => $dashboardService->refreshDate($manager),
        ]);
    }

    /**
     * @Route("/dashboard/actualiser/{mode}", name="dashboards_fetch", options={"expose"=true})
     * @param DashboardService $dashboardService
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @param int $mode
     * @return Response
     */
    public function fetch(DashboardService $dashboardService,
                          DashboardSettingsService $dashboardSettingsService,
                          EntityManagerInterface $manager,
                          int $mode): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        return $this->json([
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser, $mode),
            "refreshed" => $dashboardService->refreshDate($manager),
        ]);
    }


    /**
     * @Route(
     *     "/statistiques/receptions-associations",
     *     name="get_asso_recep_statistics",
     *     options={"expose"=true},
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
     */
    public function getAssoRecepStatistics(Request $request,
                                           EntityManagerInterface $entityManager,
                                           DashboardSettingsService $dashboardSettingsService): Response
    {
        $componentTypeRepository = $entityManager->getRepository(ComponentType::class);
        $componentType = $componentTypeRepository->findOneBy([
            'meterKey' => ComponentType::RECEIPT_ASSOCIATION
        ]);
        $data = $dashboardSettingsService->serializeValues($entityManager, $componentType, $request->query->all());
        return new JsonResponse($data);
    }

    /**
     * @Route(
     *     "/statistiques/arrivages-um",
     *     name="get_arrival_um_statistics",
     *     options={"expose"=true},
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DashboardSettingsService $dashboardSettingsService
     * @return Response
     */
    public function getArrivalUmStatistics(Request $request,
                                           EntityManagerInterface $entityManager,
                                           DashboardSettingsService $dashboardSettingsService): Response
    {
        $componentTypeRepository = $entityManager->getRepository(ComponentType::class);
        $componentType = $componentTypeRepository->findOneBy([
            'meterKey' => ComponentType::DAILY_ARRIVALS
        ]);
        $data = $dashboardSettingsService->serializeValues($entityManager, $componentType, $request->query->all());
        return new JsonResponse($data);
    }


}
