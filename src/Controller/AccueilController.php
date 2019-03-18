<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


use App\Repository\AlerteRepository;

/**
 * @Route("/accueil")
 */

class AccueilController extends AbstractController
{
    /**
     * @Route("/", name="accueil", methods={"GET"})
     */
    public function index(AlerteRepository $arlerteRepository, Request $request): Response
    {  
        $nbAlerte = $arlerteRepository->countAlertes();

        return $this->render('accueil/index.html.twig', [
            'nbAlerte' => $nbAlerte,
        ]);
    }
}
