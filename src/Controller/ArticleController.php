<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Repository\ArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\CollecteRepository;
use App\Repository\ReceptionRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Service\RefArticleDataService;
use App\Service\ArticleDataService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\Article;
use Proxies\__CG__\App\Entity\ReferenceArticle;
use App\Entity\ValeurChampsLibre;

/**
 * @Route("/article")
 */
class ArticleController extends AbstractController
{

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var CollecteRepository
     */
    private $collecteRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var RefArticleDataService
     */
    private $refArticleDataService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(FournisseurRepository $fournisseurRepository, ChampsLibreRepository $champsLibreRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, ArticleDataService $articleDataService, TypeRepository $typeRepository, RefArticleDataService $refArticleDataService, ArticleFournisseurRepository $articleFournisseurRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, StatutRepository $statutRepository, ArticleRepository $articleRepository, EmplacementRepository $emplacementRepository, CollecteRepository $collecteRepository, UserService $userService)
    {
        $this->fournisseurRepository = $fournisseurRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleRepository = $articleRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->collecteRepository = $collecteRepository;
        $this->receptionRepository = $receptionRepository;
        $this->typeRepository = $typeRepository;
        $this->refArticleDataService = $refArticleDataService;
        $this->articleDataService = $articleDataService;
        $this->userService = $userService;
    }

    /**
     * @Route("/", name="article_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }
        return $this->render('article/index.html.twig', [
            'valeurChampsLibre' => null,
            'type' => $this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE)
        ]);
    }

    /**
     * @Route("/api", name="article_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $articles = $this->articleRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $url['edit'] = $this->generateUrl('demande_article_edit', ['id' => $article->getId()]);

                $rows[] =
                    [
                        'id' => ($article->getId() ? $article->getId() : 'Non défini'),
                        'Référence' => ($article->getReference() ? $article->getReference() : 'Non défini'),
                        'Statut' => ($article->getStatut() ? $article->getStatut()->getNom() : 'Non défini'),
                        'Libellé' => ($article->getLabel() ? $article->getLabel() : 'Non défini'),
                        'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
                        'Quantité' => ($article->getQuantite() ? $article->getQuantite() : 'Non défini'),
                        'Actions' => $this->renderView('article/datatableArticleRow.html.twig', [
                            'url' => $url,
                            'articleId' => $article->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/voir", name="article_show", options={"expose"=true},  methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $article = $this->articleRepository->find($data);

            $json = $this->renderView('article/modalShowArticleContent.html.twig', [
                'article' => $article,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="article_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $article = $this->articleRepository->find($data);
            $data = $this->articleDataService->getDataEditForArticle($article);

            $json = $this->renderView('article/modalModifyArticleContent.html.twig', [
                'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                'types' => $this->typeRepository->getByCategoryLabel(Article::CATEGORIE),
                'article' => $article,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/nouveau", name="article_new", options={"expose"=true},  methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $toInsert = new Article();
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, $data['actif'] ? 'actif' : 'inactif');
            $date = new \DateTime('now');
            $ref = $date->format('YmdHis');
            $toInsert
                ->setLabel($data['libelle'])
                ->setConform(!$data['conform'])
                ->setStatut($statut)
                ->setCommentaire($data['commentaire'])
                ->setReference($ref . '-0')
                ->setArticleFournisseur($this->articleFournisseurRepository->find($data['articleFournisseur']))
                ->setType($this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE));
            dump($this->typeRepository->getOneByCategoryLabel(Article::CATEGORIE));
            $em = $this->getDoctrine()->getManager();
            $em->persist($toInsert);
            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $valeurChampLibre = $this->valeurChampsLibreRepository->getByArticleANDChampsLibre($toInsert->getId(), $champ);
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampsLibre();
                        $valeurChampLibre
                            ->addArticle($toInsert)
                            ->setChampLibre($this->champsLibreRepository->find($champ));
                        $em->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur($data[$champ]);
                    $em->flush();
                }
            }
            $em->flush();

            $articles = $this->articleRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $url['edit'] = $this->generateUrl('demande_article_edit', ['id' => $article->getId()]);

                $rows[] =
                    [
                        'id' => ($article->getId() ? $article->getId() : 'Non défini'),
                        'Référence' => ($article->getReference() ? $article->getReference() : 'Non défini'),
                        'Statut' => ($article->getStatut() ? $article->getStatut()->getNom() : 'Non défini'),
                        'Libellé' => ($article->getLabel() ? $article->getLabel() : 'Non défini'),
                        'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
                        'Quantité' => ($article->getQuantite() ? $article->getQuantite() : 'Non défini'),
                        'Actions' => $this->renderView('article/datatableArticleRow.html.twig', [
                            'url' => $url,
                            'articleId' => $article->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/api-modifier", name="article_api_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $article = $this->articleRepository->find($data['article']);
            $em = $this->getDoctrine()->getManager();

            $article->setLabel($data['label']);
            $article->setConform(($data['conform'] === '1') ? true : false);
            $article->setCommentaire($data['commentaire']);
            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $valeurChampLibre = $this->valeurChampsLibreRepository->getByArticleANDChampsLibre($article->getId(), $champ);
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampsLibre();
                        $valeurChampLibre
                            ->addArticle($article)
                            ->setChampLibre($this->champsLibreRepository->find($champ));
                        $em->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur($data[$champ]);
                    $em->flush();
                }
            }

            $em->flush();

            $articles = $this->articleRepository->findAll();
            $rows = [];
            foreach ($articles as $article) {
                $url['edit'] = $this->generateUrl('demande_article_edit', ['id' => $article->getId()]);

                $rows[] =
                    [
                        'id' => ($article->getId() ? $article->getId() : 'Non défini'),
                        'Référence' => ($article->getReference() ? $article->getReference() : 'Non défini'),
                        'Statut' => ($article->getStatut() ? $article->getStatut()->getNom() : 'Non défini'),
                        'Libellé' => ($article->getLabel() ? $article->getLabel() : 'Non défini'),
                        'Référence article' => ($article->getArticleFournisseur() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : 'Non défini'),
                        'Quantité' => ($article->getQuantite() ? $article->getQuantite() : 'Non défini'),
                        'Actions' => $this->renderView('article/datatableArticleRow.html.twig', [
                            'url' => $url,
                            'articleId' => $article->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/autocompleteArticleFournisseur", name="get_articleRef_fournisseur", options={"expose"=true})
     */
    public function getRefArticles(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('term');

            $articleFournisseur = $this->articleFournisseurRepository->findBySearch($search);
            return new JsonResponse(['results' => $articleFournisseur]);
        }
        throw new NotFoundHttpException("404");
    }

    //    /**
    //     * @Route("/get-article", name="get_article_by_refArticle", options={"expose"=true})
    //     */
    //    public function getArticleByRefArticle(Request $request): Response
    //    {
    //        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
    //            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
    //            if ($refArticle) {
    //                $json = $this->articleDataService->getArticleOrNoByRefArticle($refArticle, true);
    //            } else {
    //                $json = false; //TODO gérer erreur retour
    //            }
    //
    //            return new JsonResponse($json);
    //        }
    //        throw new NotFoundHttpException('404');
    //    }

    /**
     * @Route("/get-article-collecte", name="get_collecte_article_by_refArticle", options={"expose"=true})
     */
    public function getCollecteArticleByRefArticle(Request $request): Response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            $refArticle = null;
            if ($data['referenceArticle']) {
                $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);
            }
            if ($refArticle) {
                $json = $this->articleDataService->getCollecteArticleOrNoByRefArticle($refArticle, true);
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/get-article-demande", name="demande_article_by_refArticle", options={"expose"=true})
     */
    public function getLivraisonArticleByRefArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $refArticle = json_decode($request->getContent(), true)) {
            $refArticle = $this->referenceArticleRepository->find($refArticle);

            if ($refArticle) {
                $json = $this->articleDataService->getLivraisonArticleOrNoByRefArticle($refArticle, true);
            } else {
                $json = false; //TODO gérer erreur retour
            }

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }
}
