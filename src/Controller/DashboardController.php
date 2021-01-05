<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\DashboardSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/")
 */
class DashboardController extends AbstractController {

    /**
     * @Route("/accueil-futur", name="dashboards")
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @return Response
     */
    public function dashboards(DashboardSettingsService $dashboardSettingsService,
                               EntityManagerInterface $manager): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        return $this->render("dashboard/dashboards.html.twig", [
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser),
        ]);
    }

    /**
     * @Route("/accueil/actualiser", name="dashboards_fetch", options={"expose"=true})
     * @param DashboardSettingsService $dashboardSettingsService
     * @param EntityManagerInterface $manager
     * @return Response
     */
    public function fetch(DashboardSettingsService $dashboardSettingsService, EntityManagerInterface $manager): Response {
        /** @var Utilisateur $loggedUser */
        $loggedUser = $this->getUser();
        return $this->json([
            "dashboards" => $dashboardSettingsService->serialize($manager, $loggedUser),
        ]);
    }

}
