<?php

namespace App\Controller;

use App\Entity\Receptions;
use App\Form\ReceptionsType;
use App\Repository\ReceptionsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Articles;
use App\Form\ArticlesType;
use App\Repository\ArticlesRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseursRepository;
use App\Repository\UtilisateursRepository;

use App\Entity\ReferencesArticles;
use App\Form\ReferencesArticlesType;
use App\Repository\ReferencesArticlesRepository;

use Knp\Component\Pager\PaginatorInterface;
use App\Repository\StatutsRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpFoundation;

/**
 * @Route("/receptions")
 */
class ReceptionsController extends AbstractController
{

    /**
     * @Route("/creationReception", name="createReception", methods="POST")
     */
    public function createReception(Request $request, StatutsRepository $statutsRepository, ReceptionsRepository $receptionsRepository, FournisseursRepository $fournisseursRepository, UtilisateursRepository $utilisateursRepository) : Response
    {

        dump("dqfz");
        if(!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) //Si la requête est de type Xml et que data est attribuée
        {
            if(count($data) != 4)// On regarde si le nombre de données reçu est conforme et on envoi dans la base
            {
                $fournisseur = $fournisseursRepository->findById(intval($data[2]['fournisseur']));
                $utilisateur = $utilisateursRepository->findById(intval($data[3]['utilisateur']));

                $reception = new Receptions();
                $statut = $statutsRepository->findById(1);
                $reception->setStatut($statut[0]);
                $reception->setDate(new \DateTime('now'));
                $reception->setNumeroArrivage($data[0]['NumeroArrivage']);
                $reception->setNumeroReception($data[1]['NumeroReception']);
                $reception->setFournisseur($fournisseur[0]);
                $reception->setUtilisateur($utilisateur[0]);
                $reception->setCommentaire($data[4]['commentaire']);
                $em = $this->getDoctrine()->getManager();
                $em->persist($reception);
                $em->flush();

                $data = array(
                    'reception' => [
                        "NumeroArrivage" => $reception->getNumeroArrivage(),
                        "NumeroReception" => $reception->getNumeroReception(),
                        "Fournisseur" => $reception->getFournisseur(),
                        "Utilisateur" => $reception->getUtilisateur(),
                        "Commentaire" => $reception->getCommentaire(),
                        "Statut" => $reception->getUtilisateur()
                    ],
                    'message' => 'La réception à bien été enregistrer !'
                );
                
                dump($data);
                $data = json_encode($data);
                return new JsonResponse($data);
            }
        }

        throw new NotFoundHttp;
    }

    /**
     * @Route("/{history}", name="receptions_index")
     */
    public function index(ReceptionsRepository $receptionsRepository, FournisseursRepository $fournisseursRepository, UtilisateursRepository $utilisateurRepository, StatutsRepository $statutsRepository, PaginatorInterface $paginator, Request $request, $history): Response
    {
        $fournisseursRepository = $fournisseursRepository->findAll();
        $utilisateurRepository = $utilisateurRepository->findAll();

        /* On regarde si l'history = 1 , si oui alors on récupère la requête findAll sinon findByDateOrStatut */
        $date = ($history === '1') ? null : new \DateTime('now') ;
        $historyQuery = ($history === '1') ? $receptionsRepository->findAll() : $receptionsRepository->findByDateOrStatut($date);

        // /* Pagination grâce au bundle Knp Paginator */
        $pagination = $paginator->paginate(
            $historyQuery, /* On récupère la requête en fonction de history et on la pagine */
            $request->query->getInt('page', 1),
            10
        );

        if($history === '1'){
            return $this->render('receptions/index.html.twig', [
                'receptions' => $pagination,
                'fournisseurs' => $fournisseursRepository,
                'utilisateurs' => $utilisateurRepository
            ]);
        }
        else
        {
            //filtrage par la date du jour et le statut, requete SQL dédié
            return $this->render('receptions/index.html.twig', [
                'receptions' => $pagination,
                'fournisseurs' => $fournisseursRepository,
                'utilisateurs' => $utilisateurRepository,
                'date' => $date = date("d-m-y")
            ]);
        }    
    }

    /**
     * @Route("/show/{id}", name="receptions_show", methods="GET")
     */
    public function show(Receptions $reception): Response
    {
        return $this->render('receptions/show.html.twig', ['reception' => $reception]);
    }

    /**
     * @Route("/{id}/edit", name="receptions_edit", methods="GET|POST")
     */
    public function edit(Request $request, Receptions $reception): Response
    {
        $form = $this->createForm(ReceptionsType::class, $reception);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) 
        {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('receptions_index', ['history'=>'0']);
        }

        return $this->render('receptions/edit.html.twig', [
            'reception' => $reception,
            'form' => $form->createView(),
        ]);

    }

    /**
     * @Route("/{id}", name="receptions_delete", methods="DELETE")
     */
    public function delete(Request $request, Receptions $reception): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reception->getId(), $request->request->get('_token'))) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($reception);
            $em->flush();
        }
        return $this->redirectToRoute('receptions_index');
    }

    /**
     * @Route("/article/{id}/{k}", name="reception_ajout_article", methods="GET|POST")
     */
    public function ajoutArticle(Request $request, Receptions $reception, ArticlesRepository $articlesRepository, StatutsRepository $statutsRepository, EmplacementRepository $emplacementRepository, ReferencesArticlesRepository $referencesArticlesRepository , $id, $k): Response
    {
        //findByReception requete SQl dédié 
        $article = new Articles();
        $form = $this->createForm(ArticlesType::class, $article);
        $form->handleRequest($request);
        
        //création des articles en relation avec la reception
        if ($form->isSubmitted() && $form->isValid() && $k == false) 
        {
            if ($article->getEtat())
            {
                $statut = $statutsRepository->findById(1);
                $article->setStatut($statut[0]);
            }
            else 
            {
                $statut = $statutsRepository->findById(5);
                $article->setStatut($statut[0]);
            }

            $article->setReception($reception);
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('reception_ajout_article', array('id'=> $id, 'k'=>0));
        }
        //fin de reception/mise en stock des articles
        // k sert à vérifier et identifier la fin de la reception, en suite on modifie les "setStatut" des variables 
        if ($k)
        {
            $articles =  $articlesRepository->findByReception($id);
            // modification du statut
            foreach ($articles as $article) 
            {
                $statut = $statutsRepository->findById(1);
                //vérifie si l'article est bien encore en reception

                if ($article->getStatut() === $statut[0]  && $article->getEtat() === true)
                {
                    $statut = $statutsRepository->findById(2);
                    $article->setStatut($statut[0]);
                }
            }

            $statut = $statutsRepository->findById(7);
            $reception->setStatut($statut[0]);

            //calcul de la quantite des stocks par artciles de reference
            $refArticles = $referencesArticlesRepository->findAll();

            foreach ($refArticles as $refArticle) 
            {
                // requete Count en SQL dédié
                $quantityRef = $articlesRepository->findCountByRefArticle($refArticle);
                $quantity = $quantityRef[0];
                $refArticle->setQuantity($quantity[1]);
                dump($quantity);
            }

            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('receptions_index', array('history'=> 0));

        }

        return $this->render("receptions/ajoutArticle.html.twig", array(
            'reception' => $reception,
            'emplacement' => $emplacementRepository->findAll(),
            'formView' => $form->createView(),
            'id'=> $id,    
        ));
    }

}
