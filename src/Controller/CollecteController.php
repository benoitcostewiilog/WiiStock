<?php

namespace App\Controller;

use App\Entity\Collecte;
use App\Form\CollecteType;
use App\Repository\CollecteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\EmplacementRepository;
use App\Repository\StatutRepository;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/collecte")
 */
class CollecteController extends AbstractController
{
    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;
    
    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    public function __construct(StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UtilisateurRepository $utilisateurRepository)
    {
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->collecteRepository = $collecteRepository;
        $this->utilisateurRepository = $utilisateurRepository;
    }


    /**
     * @Route("/apiCollecte", name="collecte_api", options={"expose"=true}, methods={"GET", "POST"}) 
     */
    public function collecteApi(Request $request) : Response
    {
        if ($request->isXmlHttpRequest()) //Si la requête est de type Xml
        {
            $collectes = $this->collecteRepository->findAll();
            
            $rows = [];
            foreach ($collectes as $collecte) {
                $url['edit'] = $this->generateUrl('collecte_edit', ['id' => $collecte->getId()] );
                // $url = $this->generateUrl('collecte_ajout_article', ['id' => $collecte->getId(), 
                // 'finishCollecte'=>'0'] );
                $rows[] = [
                    'id' => ($collecte->getId() ? $collecte->getId() : "Non défini"),
                    'Date'=> ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
                    'Demandeur'=> ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null ),
                    'Objet'=> ($collecte->getObjet() ? $collecte->getObjet() : null ),
                    'Statut'=> ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
                    'Actions' => $this->renderView('collecte/datatableCollecteRow.html.twig', [
                        'url' => $url,
                        'collecte' => $collecte,
                        'collecteId'=>$collecte->getId()
                        ])
                        
                ];
                
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/", name="collecte_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
             return $this->render('collecte/index.html.twig', [
              'emplacements'=>$this->emplacementRepository->findAll(),
              'collecte'=>$this->collecteRepository->findAll(),
              'statuts'=>$this->statutRepository->findAll(),
        ]);
    }

    /**
     * @Route("/creer", name="collecte_create", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function creationCollecte(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getEntityManager();    
            $date = new \DateTime('now');
            $status = $this->statutRepository->findOneByNom(Collecte::STATUS_DEMANDE);
            $numero = "C-". $date->format('YmdHis');
          
            $collecte = new Collecte;
            $collecte
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
                ->setNumero($numero)
                ->setDate($date)
                ->setStatut($status)
                ->setPointCollecte($this->emplacementRepository->find($data['Pcollecte']))
                ->setObjet($data['Objet'])
                ->setCommentaire($data['commentaire']);

           
            $em->persist($collecte);
            $em->flush();

        // $url = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
        // $data = [
        //     'Date'=> ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
        //     'Demandeur'=> ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null ),
        //     'Libellé'=> ($collecte->getObjet() ? $collecte->getObjet() : null ),
        //     'Statut'=> ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
        //     'Actions' => $this->renderView('collecte/datatableCollecteRow.html.twig', [
        //         // 'url' => $url
        //         ])
        // ];
       
            return new JsonResponse($data);
        }
        throw new XmlHttpException("404 not found");
    }
    

    // /**
    //  * @Route("/ajouter-article", name="collecte_add_article")
    //  */
    // public function addArticle(Request $request): Response
    // {
    //     $articleId = $request->request->getInt('articleId');
    //     $quantity = $request->request->getInt('quantity');
    //     $collecteId = $request->request->getInt('collecteId');

    //     $article = $this->articleRepository->find($articleId);
    //     $collecte = $this->collecteRepository->find($collecteId);

    //     $article
    //         ->setQuantiteCollectee($quantity)
    //         ->addCollecte($collecte);

    //     $em = $this->getDoctrine()->getManager();
    //     $em->persist($article);
    //     $em->flush();

    //     $data = [
    //         'Référenceom'=>( $article->getReference() ?  $article->getReference()():""),
    //         'Statut'=> ($article->getStatut()->getNom() ? $article->getStatut()->getNom() : ""),
    //         'Conformité'=>($article->getEtat() ? 'conforme': 'anomalie'),
    //         'Référence Article'=> ($article->getRefArticle() ? $article->getRefArticle()->getLibelle() : ""),
    //         'Quantité à collecter'=>($article->getQuantiteCollectee() ? $article->getQuantiteCollectee() : ""),
    //         'Actions' => $this->renderView('collecte/datatableArticleRow.html.twig', ['article' => $article])
    //     ];

    //     return new JsonResponse($data);
    // }

    // /**
    //  * @Route("/retirer-article", name="collecte_remove_article")
    //  */
    // public function removeArticle(Request $request)
    // {
    //     $articleId = $request->request->getInt('articleId');
    //     $collecteId = $request->request->getInt('collecteId');

    //     $article = $this->articleRepository->find($articleId);
    //     $collecte = $this->collecteRepository->find($collecteId);

    //     if (!empty($article)) {
    //         $article->removeCollecte($collecte);
    //         $em = $this->getDoctrine()->getManager();
    //         $em->persist($article);
    //         $em->flush();
    //         return new JsonResponse(true);
    //     } else {
    //         return new JsonResponse(false);
    //     }

    // }

    // /**
    //  * @Route("/api", name="collectes_json", options={"expose"=true}, methods={"GET", "POST"})
    //  */
    // public function getCollectes(): Response
    // {
    //     $collectes = $this->collecteRepository->findAll();
    //     $rows = [];
    //     foreach ($collectes as $collecte) {
    //         $url['show'] = $this->generateUrl('collecte_show', ['id' => $collecte->getId()]);
    //         $rows[] = [
    //             'Date'=> ($collecte->getDate() ? $collecte->getDate()->format('d/m/Y') : null),
    //             'Demandeur'=> ($collecte->getDemandeur() ? $collecte->getDemandeur()->getUserName() : null ),
    //             'Libellé'=> ($collecte->getObjet() ? $collecte->getObjet() : null ),
    //             'Statut'=> ($collecte->getStatut()->getNom() ? ucfirst($collecte->getStatut()->getNom()) : null),
    //             'Actions' => $this->renderView('collecte/datatableCollecteRow.html.twig', ['url' => $url])
    //         ];
    //     }
    //     $data['data'] = $rows;

    //     return new JsonResponse($data);
    // }
//
//    /**
//     * @Route("{id}/finish", name="finish_collecte")
//     */
//    public function finishCollecte(Collecte $collecte, StatutRepository $statutRepository)
//    {
//        $em = $this->getDoctrine()->getManager();
//
//        // changement statut collecte
//        $statusFinCollecte = $statutRepository->findOneBy(['nom' => Collecte::STATUS_FIN]);
//        $collecte->setStatut($statusFinCollecte);
//
//        // changement statut article
//        $statusEnStock = $statutRepository->findOneBy(['nom' => Articles::STATUS_EN_STOCK]);
//        $article = $collecte->getArticles();
//        foreach ($article as $article) {
//            $article->setStatut($statusEnStock);
//            $em->persist($article);
//        }
//        $em->flush();
//    }

    // /**
    //  * @Route("/{id}", name="collecte_show", methods={"GET", "POST"})
    //  */
    // public function show(Collecte $collecte): Response
    // {
    //     return $this->render('collecte/show.html.twig', [
    //         'collecte' => $collecte,
    //     ]);
    // }

    // /**
    //  * @Route("/{id}/modifier", name="collecte_edit", methods={"GET","POST"})
    //  */
    // public function edit(Request $request, Collecte $collecte): Response
    // {
    //     $form = $this->createForm(CollecteType::class, $collecte);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) 
    //     {
    //         $this->getDoctrine()->getManager()->flush();
    //         return $this->redirectToRoute('collecte_index', [
    //             'id' => $collecte->getId(),
    //         ]);
    //     }

    //     return $this->render('collecte/edit.html.twig', [
    //         'collecte' => $collecte,
    //         'form' => $form->createView(),
    //     ]);
    // }

/**
     * @Route("/editApi", name="collecte_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $collecte = $this->collecteRepository->find($data);
           
            $json = $this->renderView('collecte/modalEditCollecteContent.html.twig', [
                'collecte' => $collecte,
                "statuts" => $this->statutRepository->findAll(),
                "emplacements" => $this->emplacementRepository->findAll(),
                // 'utilisateurs'=>$this->utilisateurRepository->findAll(),
            ]);
        
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/edit", name="collecte_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $collecte = $this->collecteRepository->find($data['collecte']);
            $collecte
                ->setNumero($data["NumeroCollecte"])
                ->setDate(new \DateTime($data["date-collecte"]))
                ->setCommentaire($data["commentaire"])
                ->setObjet($data["objet"])
                // ->setStatut($data['Statuts'])
                ->setPointCollecte(($data["Pcollecte"]));
              
            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimerCollecte", name="collecte_delete", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteCollecte(Request $request) : Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {       
            $collecte = $this->collecteRepository->find($data['collecte']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($collecte);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException("404");
    }
}
