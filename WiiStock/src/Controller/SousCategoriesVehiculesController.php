<?php

namespace App\Controller;

use App\Entity\SousCategoriesVehicules;
use App\Form\SousCategoriesVehiculesType;
use App\Repository\SousCategoriesVehiculesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parc/admin/sous_categories_vehicules")
 */
class SousCategoriesVehiculesController extends Controller
{
    /**
     * @Route("/", name="sous_categories_vehicules_index", methods="GET")
     */
    public function index(SousCategoriesVehiculesRepository $sousCategoriesVehiculesRepository): Response
    {
        return $this->render('sous_categories_vehicules/index.html.twig', ['sous_categories_vehicules' => $sousCategoriesVehiculesRepository->findAll()]);
    }

    /**
     * @Route("/new", name="sous_categories_vehicules_new", methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        $sousCategoriesVehicule = new SousCategoriesVehicules();
        $form = $this->createForm(SousCategoriesVehiculesType::class, $sousCategoriesVehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($sousCategoriesVehicule);
            $em->flush();

            return $this->redirectToRoute('sous_categories_vehicules_index');
        }

        return $this->render('sous_categories_vehicules/new.html.twig', [
            'sous_categories_vehicule' => $sousCategoriesVehicule,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="sous_categories_vehicules_show", methods="GET")
     */
    public function show(SousCategoriesVehicules $sousCategoriesVehicule): Response
    {
        return $this->render('sous_categories_vehicules/show.html.twig', ['sous_categories_vehicule' => $sousCategoriesVehicule]);
    }

    /**
     * @Route("/{id}/edit", name="sous_categories_vehicules_edit", methods="GET|POST")
     */
    public function edit(Request $request, SousCategoriesVehicules $sousCategoriesVehicule): Response
    {
        $form = $this->createForm(SousCategoriesVehiculesType::class, $sousCategoriesVehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('sous_categories_vehicules_edit', ['id' => $sousCategoriesVehicule->getId()]);
        }

        return $this->render('sous_categories_vehicules/edit.html.twig', [
            'sous_categories_vehicule' => $sousCategoriesVehicule,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="sous_categories_vehicules_delete", methods="DELETE")
     */
    public function delete(Request $request, SousCategoriesVehicules $sousCategoriesVehicule): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sousCategoriesVehicule->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($sousCategoriesVehicule);
            $em->flush();
        }

        return $this->redirectToRoute('sous_categories_vehicules_index');
    }
}
