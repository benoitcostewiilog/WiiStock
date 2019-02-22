<?php

namespace App\Controller;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;
use App\Repository\StatutsRepository;
use App\Repository\CollecteRepository;
use App\Repository\ReceptionsRepository;
use App\Repository\EmplacementRepository;
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
     * @var StatutsRepository
     */
    private $statutsRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticlesRepository
     */
    private $articlesRepository;
    
    /**
     * @var UtilisateursRepository
     */
    private $utilisateurRepository;
    
    /**
     * @var ReceptionsRepository
     */
    private $receptionsRepository;

    public function __construct(ReceptionsRepository $receptionsRepository, StatutsRepository $statutsRepository, ArticlesRepository $articlesRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository)
    {
        $this->statutsRepository = $statutsRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articlesRepository = $articlesRepository;
        $this->collecteRepository = $collecteRepository;
        $this->receptionRepository = $receptionsRepository;
    }

    /**
     * @Route("/", name="articles_index", methods={"GET", "POST"})
     */
    public function index(Request $request) : Response
    {
        return $this->render('articles/index.html.twig');
    }

    /**
     * @Route("/api", name="articles_api", methods="GET|POST")
     */
    public function articleApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $articles = $this->articlesRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $urlEdite = $this->generateUrl('articles_edit', ['id' => $article->getId()] );
                $urlShow = $this->generateUrl('articles_show', ['id' => $article->getId()]);
                $row = 
                [
                    'id' => ($article->getId() ? $article->getId() : "Non défini"),
                    'Nom' => ($article->getNom() ? $article->getNom() : "Non défini"),
                    'Statut' => ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : "Non défini"),
                    'Reférence article' => ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : "Non défini"),
                    'Emplacement' => ($article->getPosition() ? $article->getPosition()->getNom() : "Non défini"),
                    'Destination' => ($article->getDirection() ? $article->getDirection()->getNom() : "Non défini"),
                    'Quantité' => ($article->getQuantite() ? $article->getQuantite() : "Non défini"),
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
     * @Route("/par-collecte", name="articles_by_collecte", methods={"GET", "POST"})
     */
    public function getArticlesByCollecte(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $collecteId = $request->get('collecteId');
            $collecte = $this->collecteRepository->find($collecteId);
            $articles = $collecte->getArticles();
            $rows = [];
            foreach ($articles as $article) {
                $rows[] = [
                    'Nom'=>( $article->getNom() ?  $article->getNom():""),
                    'Statut'=> ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : ""),
                    'Références Articles'=> ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : ""),
                    'Emplacement'=> ($article->getPosition() ? $article->getPosition()->getNom() : ""),
                    'Destination'=> ($article->getDirection() ? $article->getDirection()->getNom() : ""),
                    'Quantité à collecter'=>($article->getQuantiteCollectee() ? $article->getQuantiteCollectee() : ""),
                    'Actions'=> "<div class='btn btn-xs btn-default article-edit' onclick='editRow($(this))' data-toggle='modal' data-target='#modalModifyArticle' data-quantity='" . $article->getQuantiteCollectee(). "' data-name='" . $article->getNom() . "' data-id='" . $article->getId() . "'><i class='fas fa-pencil-alt fa-2x'></i></div>
                        <div class='btn btn-xs btn-default article-delete' onclick='deleteRow($(this))' data-id='" . $article->getId() . "'><i class='fas fa-trash fa-2x'></i></div>"
                ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/nouveau", name="articles_new", methods="GET|POST")  INUTILE
     */
    public function new(Request $request) : Response
    {
        $article = new Articles();
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $statut = $this->statutsRepository->findOneByCategorieAndStatut(Articles::CATEGORIE, Articles::STATUT_RECEPTION_EN_COURS);
            $article->setStatut($statut);
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
     * @Route("/voir/{id}", name="articles_show", methods="GET")
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
     * @Route("/ajouter", name="modal_add_article")
     */
    public function displayModalAddArticle()
    {
        $articles = $this->articlesRepository->findAllSortedByName();

        $html = $this->renderView('collecte/modalAddArticleContent.html.twig', [
            'articles' => $articles
        ]);

        return new JsonResponse(['html' => $html]);
    }

    /**
     * @Route("/modifier/{id}", name="articles_edit", methods="GET|POST")
     */
    public function edit(Request $request, Articles $article) : Response
    {
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid())
        {
            $reception = $article->getReception()->getId();
            $reception = $this->receptionsRepository->findOneById($reception);
            $articles = $reception->getArticles();
            foreach($articles as $article) {
                if($article->getStatut()->getId() == 5) {
                    $statut = $this->statutsRepository->findOneById(5);
                    $reception->setStatut($statut);
                    break;
                }
                else {
                    $statut = $this->statutsRepository->findOneById(6);
                    $reception->setStatut($statut);
                }
            }

            $this->getDoctrine()->getManager()->flush();

            return $this->redirect($_POST['url']);
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
        return $this->redirectToRoute('articles_index');
    }

    /**
     * @Route("/modifier-quantite", name="edit_quantity")
     */
    public function editQuantity(Request $request)
    {
        $articleId = $request->request->get('articleId');
        $quantity = $request->request->get('quantity');

        $article = $this->articlesRepository->find($articleId);
        $article->setQuantiteCollectee($quantity);

        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();

        return new JsonResponse(true);
    }
}
