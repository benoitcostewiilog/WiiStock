<?php

namespace App\Controller;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;
use App\Repository\StatutsRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/articles")
 */
class ArticlesController extends AbstractController
{
    /**
     * @Route("/", name="articles_index", methods={"GET", "POST"})
     */
    public function index(ArticlesRepository $articlesRepository, StatutsRepository $statutsRepository, PaginatorInterface $paginator, Request $request) : Response
    {
        return $this->render('articles/index.html.twig');
    }

    /**
     * @Route("/api", name="articles_api", methods="GET|POST")
     */
    public function articleFiltreJson(ArticlesRepository $articlesRepository, Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $articles = $articlesRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $urlEdite = $this->generateUrl('articles_edit', ['id' => $article->getId()] );
                $urlShow = $this->generateUrl('articles_show', ['id' => $article->getId()] );
                $row = [
                    'id' => ($article->getId() ? $article->getId() : "null"),
                    'Nom' => ($article->getNom() ? $article->getNom() : "null"),
                    'Statut' => ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : "null"),
                    'Conformité' => ($article->getEtat() ? 'conforme' : 'anomalie'),
                    'Reférences Articles' => ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : "null"),
                    'position' => ($article->getPosition() ? $article->getPosition()->getNom() : "null"),
                    'destination' => ($article->getDirection() ? $article->getDirection()->getNom() : "null"),
                    'Quantite' => ($article->getQuantite() ? $article->getQuantite() : "null"),
                    'actions' => "<a href='" . $urlEdite . "' class='btn btn-xs btn-default command-edit'><i class='fas fa-pencil-alt fa-2x'></i></a>
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
     * @Route("/new", name="articles_new", methods="GET|POST")  INUTILE
     */
    public function new(Request $request, StatutsRepository $statutsRepository) : Response
    {
        $article = new Articles();
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $statut = $statutsRepository->findById(1);
            $article->setStatut($statut[0]);
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();
            return $this->redirectToRoute('articles_index');
        }

        return $this->render('articles/new.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/show/{id}", name="articles_show", methods="GET")
     */
    public function show(Articles $article) : Response
    {
        $session = $_SERVER['HTTP_REFERER'];

        return $this->render('articles/show.html.twig', [
            'article' => $article,
            'session' => $session
        ]);
    }

    /**
     * @Route("/edite/{id}", name="articles_edit", methods="GET|POST")
     */
    public function edit(Request $request, Articles $article, StatutsRepository $statutsRepository) : Response
    {
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($article->getEtat() === false) {
                $statut = $statutsRepository->findById(5);
                $article->setStatut($statut[0]);
            }
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('articles_index', ['statut' => 'all', 'id' => 0, ]);
        }
        return $this->render('articles/edit.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
            'id' => $article->getReception()->getId(),
        ]);
    }

    /**
     * @Route("/{id}", name="articles_delete", methods="DELETE")
     */
    public function delete(Request $request, Articles $article) : Response
    {
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();
        }
        return $this->redirectToRoute('articles_index', ['statut' => 'all', 'id' => 0, ]);
    }
}
