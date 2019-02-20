<?php

namespace App\Controller;

use App\Entity\Fournisseurs;
use App\Form\FournisseursType;
use App\Repository\FournisseursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;

/**
 * @Route("/stock/fournisseurs")
 */
class FournisseursController extends Controller
{
    /**
     * @Route("/get", name="fournisseurs_get", methods="GET")
     */
    public function getReferencesArticles(Request $request, FournisseursRepository $fournisseursRepository) : Response
    {
        if ($request->isXmlHttpRequest()) {
            $q = $request->query->get('q');
            $refs = $fournisseursRepository->findBySearch($q);
            $rows = array();
            foreach ($refs as $ref) {
                $row = [
                    "id" => $ref->getId(),
                    "nom" => $ref->getNom(),
                    "code_reference" => $ref->getCodeReference(),
                ];
                array_push($rows, $row);
            }

            $data = array(
                "total_count" => count($rows),
                "items" => $rows,
            );
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404 not found');
    }

    /**
     * @Route("/api", name="fournisseur_api", methods="GET")
     */
    public function fournisseursApi(Request $request, FournisseursRepository $fournisseursRepository) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $refs = $fournisseursRepository->findAll();
            $rows = [];
            foreach ($refs as $fournisseur) {
                $urlEdite = $this->generateUrl('fournisseurs_edit', ['id' => $fournisseur->getId()]);
                $urlShow = $this->generateUrl('fournisseurs_show', ['id' => $fournisseur->getId()]);
                $row = [
                    "Nom" => $fournisseur->getNom(),
                    "Code de réference" => $fournisseur->getCodeReference(),
                    'Actions' => "<a href='" . $urlEdite . "' class='btn btn-xs btn-default command-edit'><i class='fas fa-pencil-alt fa-2x'></i></a>
                    <a href='" . $urlShow . "' class='btn btn-xs btn-default command-edit '><i class='fas fa-eye fa-2x'></i></a>",
                ];
                array_push($rows, $row);
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="fournisseurs_index", methods="GET")
     */
    public function index(FournisseursRepository $fournisseursRepository, Request $request, PaginatorInterface $paginator) : Response
    {

        $pagination = $paginator->paginate(
            $fournisseursRepository->findAll(),
            $request->query->getInt('page', 1),
            2
        );
        return $this->render('fournisseurs/index.html.twig', ['fournisseurs' => $pagination]);
    }

    /**
     * @Route("/creation/fournisseur", name="creation_fournisseur", methods="GET|POST")
     */
    public function creationFournisseur(Request $request) : Response
    {
        $em = $this->getDoctrine()->getEntityManager();

        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $fournisseur = new Fournisseurs();
            $fournisseur->setNom($data[0]["Nom"]);
            $fournisseur->setCodeReference($data[1]["Code"]);
            $em->persist($fournisseur);
            $em->flush();
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/{id}", name="fournisseurs_show", methods="GET")
     */
    public function show(Fournisseurs $fournisseur) : Response
    {
        return $this->render('fournisseurs/show.html.twig', ['fournisseur' => $fournisseur]);
    }

    /**
     * @Route("/{id}/edit", name="fournisseurs_edit", methods="GET|POST")
     */
    public function edit(Request $request, Fournisseurs $fournisseur) : Response
    {
        $form = $this->createForm(FournisseursType::class, $fournisseur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('fournisseurs_index');
        }

        return $this->render('fournisseurs/edit.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="fournisseurs_delete", methods="DELETE")
     */
    public function delete(Request $request, Fournisseurs $fournisseur) : Response
    {
        $receptions = $fournisseur->getreceptions();
        dump(count($receptions));
        if (count($receptions) === 0) {
            if ($this->isCsrfTokenValid('delete' . $fournisseur->getId(), $request->request->get('_token'))) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($fournisseur);
                $em->flush();
                return $this->redirectToRoute('fournisseurs_index');
            }
        } else {
            return $this->redirect($_SERVER['HTTP_REFERER']);
        }
    }
}

// , array(
//     'message' => 'impossible de supprimer un fournisseur utilisé'
// )