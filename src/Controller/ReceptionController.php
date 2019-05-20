<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Reception;
use App\Entity\ReceptionReferenceArticle;

use App\Form\ReceptionType;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ReceptionRepository;
use App\Service\UserService;
use App\Repository\ReceptionReferenceArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\ValeurChampsLibre;
use App\Repository\ChampsLibreRepository;
use App\Repository\ValeurChampsLibreRepository;
use App\Repository\TypeRepository;


use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;

use App\Entity\Emplacement;
use App\Form\EmplacementType;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\UtilisateurRepository;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\Reference;
use App\Repository\DimensionsEtiquettesRepository;
use Proxies\__CG__\App\Entity\ArticleFournisseur;
use App\Service\ArticleDataService;
use Proxies\__CG__\App\Entity\CategoryType;

/**
 * @Route("/reception")
 */
class ReceptionController extends AbstractController
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
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ChampslibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var ReceptionReferenceArticleRepository
     */
    private $receptionReferenceArticleRepository;

    /**
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampsLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var DimensionsEtiquettesRepository
     */
    private $dimensionsEtiquettesRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var ArticleDataService
     */
    private $articleDataService;


    public function __construct(ArticleDataService $articleDataService, DimensionsEtiquettesRepository $dimensionsEtiquettesRepository, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, ValeurChampsLibreRepository $valeurChampsLibreRepository, FournisseurRepository $fournisseurRepository, StatutRepository $statutRepository, ReferenceArticleRepository $referenceArticleRepository, ReceptionRepository $receptionRepository, UtilisateurRepository $utilisateurRepository, EmplacementRepository $emplacementRepository, ArticleRepository $articleRepository, ArticleFournisseurRepository $articleFournisseurRepository, UserService $userService, ReceptionReferenceArticleRepository $receptionReferenceArticleRepository)
    {
        $this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
        $this->statutRepository = $statutRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->receptionRepository = $receptionRepository;
        $this->receptionReferenceArticleRepository = $receptionReferenceArticleRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleRepository = $articleRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->valeurChampsLibreRepository = $valeurChampsLibreRepository;
        $this->typeRepository = $typeRepository;
        $this->userService = $userService;
        $this->articleDataService = $articleDataService;
    }


    /**
     * @Route("/new", name="reception_new", options={"expose"=true}, methods="POST")
     */
    public function new(Request $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            $fournisseur = $this->fournisseurRepository->find(intval($data['fournisseur']));
            $type = $this->typeRepository->find(intval($data['type']));
            $reception = new Reception();

            if ($data['anomalie'] == true) {
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_ANOMALIE);
            } else {
                $statut = $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_EN_ATTENTE);
            }

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $numeroReception = 'R' . $date->format('ymd-His'); //TODO CG ajouter numéro

            $reception
                ->setStatut($statut)
                ->setNumeroReception($numeroReception)
                ->setDate(new \DateTime($data['date-commande']))
                ->setDateAttendu(new \DateTime($data['date-attendu']))
                ->setFournisseur($fournisseur)
                ->setReference($data['reference'])
                ->setUtilisateur($this->getUser())
                ->setType($type)
                ->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getManager();
            $em->persist($reception);
            $em->flush();

            $champsLibreKey = array_keys($data);

            foreach ($champsLibreKey as $champs) {
                if (gettype($champs) === 'integer') {
                    $valeurChampLibre = new ValeurChampsLibre();
                    $valeurChampLibre
                        ->setValeur($data[$champs])
                        ->addReception($reception)
                        ->setChampLibre($this->champsLibreRepository->find($champs));

                    $em->persist($valeurChampLibre);
                    $em->flush();
                }
            }

            $data = [
                "redirect" => $this->generateUrl('reception_show', [
                    'id' => $reception->getId(),
                    'reception' =>  $reception,
                ])
            ];
            return new JsonResponse($data);
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="reception_edit", options={"expose"=true}, methods="POST")
     */
    public function edit(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $fournisseur =  $this->fournisseurRepository->find(intval($data['fournisseur']));
            $utilisateur =  $this->utilisateurRepository->find(intval($data['utilisateur']));
            $statut =  $this->statutRepository->find(intval($data['statut']));

            $reception =  $this->receptionRepository->find($data['receptionId']);
            $reception
                ->setNumeroReception($data['NumeroReception'])
                ->setDate(new \DateTime($data['date-commande']))
                ->setDateAttendu(new \DateTime($data['date-attendu']))
                ->setStatut($statut)
                ->setFournisseur($fournisseur)
                ->setUtilisateur($utilisateur)
                ->setCommentaire($data['commentaire']);

            $em =  $this->getDoctrine()->getManager();
            $em->flush();

            $champsLibreKey = array_keys($data);
            foreach ($champsLibreKey as $champ) {
                if (gettype($champ) === 'integer') {
                    $champLibre = $this->champsLibreRepository->find($champ);
                    $valeurChampLibre = $this->valeurChampsLibreRepository->findOneByReceptionANDChampsLibre($reception, $champLibre);

                    // si la valeur n'existe pas, on la crée
                    if (!$valeurChampLibre) {
                        $valeurChampLibre = new ValeurChampsLibre();
                        $valeurChampLibre

                            ->addReception($reception)
                            ->setChampLibre($this->champsLibreRepository->find($champ));
                        $em->persist($valeurChampLibre);
                    }
                    $valeurChampLibre->setValeur($data[$champ]);
                    $em->flush();
                }
            }
            $type = $reception->getType();

            if ($type) {
                $valeurChampLibreTab = $this->valeurChampsLibreRepository->getByReceptionAndType($reception->getId(), $type);
            } else {
                $valeurChampLibreTab = [];
            }


            $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', [
                    'reception' =>  $reception,
                    'valeurChampLibreTab' => $valeurChampLibreTab,
                ])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }


    /**
     * @Route("/api-modifier", name="api_reception_edit", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEdit(Request  $request): Response
    {

        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $reception =  $this->receptionRepository->find($data['id']);
            $data = $this->getDataEditForReception($reception);

            $type = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE_TYPE);

            $typeChampLibre =  [];
            foreach ($type as $label) {
                $champsLibresComplet = $this->champsLibreRepository->findByTypeId($type['id']);
                $champsLibres = [];
                //création array edit pour vue
                foreach ($champsLibresComplet as $champLibre) {
                    $valeurChampReception = $this->valeurChampsLibreRepository->findOneByReceptionANDChampsLibre($reception->getId(), $champLibre);
                    $champsLibres[] = [
                        'id' => $champLibre->getId(),
                        'label' => $champLibre->getLabel(),
                        'typage' => $champLibre->getTypage(),
                        'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                        'defaultValue' => $champLibre->getDefaultValue(),
                        'valeurChampLibre' => $valeurChampReception,
                    ];
                }

                $typeChampLibre[] = [
                    'typeLabel' =>  $label['label'],
                    'typeId' => $label['id'],
                    'champsLibres' => $champsLibres,
                ];
            }


            $json =  $this->renderView('reception/modalEditReceptionContent.html.twig', [
                'reception' =>  $reception,
                'fournisseurs' =>  $this->fournisseurRepository->getNoOne($reception->getFournisseur()->getId()),
                'utilisateurs' =>  $this->utilisateurRepository->getNoOne($reception->getUtilisateur()->getId()),
                'statuts' =>  $this->statutRepository->findByCategorieName(Reception::CATEGORIE),
                'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
                'typeChampsLibres' => $typeChampLibre

                // 'isADemand' => $isADemand
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api", name="reception_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $receptions = $this->receptionRepository->findAll();
            $rows = [];
            foreach ($receptions as $reception) {
                $url = $this->generateUrl('reception_show', [
                    'id' => $reception->getId(),
                    'reception' =>  $reception,
                ]);
                $rows[] =
                    [
                        'id' => ($reception->getId()),
                        "Statut" => ($reception->getStatut() ?  $reception->getStatut()->getNom() : ''),
                        "Date" => ($reception->getDate() ?  $reception->getDate() : '')->format('d/m/Y'),
                        "Fournisseur" => ($reception->getFournisseur() ?  $reception->getFournisseur()->getNom() : ''),
                        "Référence" => ($reception->getNumeroReception() ?  $reception->getNumeroReception() : ''),
                        'Actions' =>  $this->renderView(
                            'reception/datatableReceptionRow.html.twig',
                            ['url' =>  $url, 'reception' =>  $reception]
                        ),
                    ];
            }
            $data['data'] =  $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-article/{id}", name="reception_article_api", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function articleApi(Request $request, $id): Response
    {
        if ($request->isXmlHttpRequest()) { //Si la requête est de type Xml
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $reception = $this->receptionRepository->find($id);
            $ligneArticles = $this->receptionReferenceArticleRepository->getByReception($reception);

            $rows = [];
            foreach ($ligneArticles as  $ligneArticle) {
                $rows[] =
                    [
                        "Référence CEA" => ($ligneArticle->getReferenceArticle() ?  $ligneArticle->getReferenceArticle()->getReference() : ''),
                        "Fournisseur" => ($ligneArticle->getFournisseur() ?  $ligneArticle->getFournisseur()->getNom() : ''),
                        "Libellé" => ($ligneArticle->getLabel() ?  $ligneArticle->getLabel() : ''),
                        "A recevoir" => ($ligneArticle->getQuantiteAR() ?  $ligneArticle->getQuantiteAR() : ''),
                        "Reçu" => ($ligneArticle->getQuantite() ?  $ligneArticle->getQuantite() : ''),
                        'Actions' =>  $this->renderView(
                            'reception/datatableLigneRefArticleRow.html.twig',
                            [
                                'ligneId' => $ligneArticle->getId(),
                                'type' => $ligneArticle->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE ? 'search' : 'print',
                                'refArticle' => $ligneArticle->getReferenceArticle()->getReference(),
                                'modifiable' => ($reception->getStatut()->getNom() !== (Reception::STATUT_RECEPTION_TOTALE)),
                            ]

                        ),
                    ];
            }
            $data['data'] =  $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/article-printer/{id}", name="article_printer_all", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function printerAllApi(Request  $request, $id): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $references =  $this->articleRepository->getRefByRecep($id);
            $rows = [];
            foreach ($references as   $reference) {
                $rows[] =  $reference['reference'];
            }
            return new JsonResponse($rows);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/", name="reception_index", methods={"GET", "POST"})
     */
    public function index(): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $types = $this->typeRepository->getIdAndLabelByCategoryLabel(CategoryType::RECEPTION);

        $typeChampLibre =  [];
        foreach ($types as $type) {
            $champsLibres = $this->champsLibreRepository->findByTypeId($type['id']);

            $typeChampLibre[] = [
                'typeLabel' =>  $type['label'],
                'typeId' => $type['id'],
                'champsLibres' => $champsLibres,
            ];
        }
        dump($typeChampLibre);

        return $this->render('reception/index.html.twig', [
            'typeChampsLibres' => $typeChampLibre,
            'types' => $types,
            // 'champsLibres' => $champsLibres

        ]);
    }

    /**
     * @Route("/supprimer", name="reception_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function delete(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $reception =  $this->receptionRepository->find($data['receptionId']);

            $entityManager =  $this->getDoctrine()->getManager();
            $entityManager->remove($reception);
            $entityManager->flush();
            $data = [
                "redirect" =>  $this->generateUrl('reception_index')
            ];
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/supprimer-article", name="reception_article_delete",  options={"expose"=true}, methods={"GET", "POST"})
     */
    public function deleteArticle(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle =  $this->receptionReferenceArticleRepository->find($data['ligneArticle']);
            $reception = $ligneArticle->getReception();
            $entityManager =  $this->getDoctrine()->getManager();
            $entityManager->remove($ligneArticle);
            $entityManager->flush();
            $nbArticleNotConform =  $this->receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statutLabel =  $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE,  $statutLabel);
            $reception->setStatut($statut);
            $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', ['reception' =>  $reception])
            ];
            $entityManager->flush();
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/add-article", name="reception_article_add", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addArticle(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() && $contentData = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $refArticle =  $this->referenceArticleRepository->find($contentData['referenceArticle']);
            $reception =  $this->receptionRepository->find($contentData['reception']);
            $fournisseur = $this->fournisseurRepository->find(intval($contentData['fournisseur']));
            $anomalie =  $contentData['anomalie'];
            if ($anomalie) {
                $statutRecep =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_ANOMALIE);
                $reception->setStatut($statutRecep);
            }

            // $quantite =  $contentData['quantite'];
            // $refArticle->setQuantiteStock($refArticle->getQuantiteStock() +  $quantite);

            $receptionReferenceArticle = new ReceptionReferenceArticle;
            $receptionReferenceArticle
                ->setLabel($contentData['libelle'])
                ->setAnomalie($anomalie)
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refArticle)
                ->setQuantite($contentData['quantite'])
                ->setQuantiteAR($contentData['quantiteAR'])
                ->setCommentaire($contentData['commentaire'])
                ->setReception($reception);

            if (array_key_exists('articleFournisseur', $contentData) && $contentData['articleFournisseur'] !== null) {
                $articleFournisseur = $this->articleFournisseurRepository->find($contentData['articleFournisseur']);
                $receptionReferenceArticle
                    ->setArticleFournisseur($articleFournisseur);
            }
            $em =  $this->getDoctrine()->getManager();
            $em->persist($receptionReferenceArticle);
            $em->flush();
            $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', ['reception' =>  $reception])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier-article", name="reception_article_edit_api", options={"expose"=true},  methods="GET|POST")
     */
    public function apiEditArticle(Request  $request): Response
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }

            $ligneArticle = $this->receptionReferenceArticleRepository->find($data['id']);

            $json =  $this->renderView(
                'reception/modalModifyLigneArticleContent.html.twig',
                ['ligneArticle' => $ligneArticle]
            );
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier-article", name="reception_article_edit", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function editArticle(Request  $request): Response
    {
        if (!$this->userService->hasRightFunction(Menu::STOCK, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) { //Si la requête est de type Xml
            $receptionReferenceArticle =  $this->receptionReferenceArticleRepository->find($data['article']);
            $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
            $refArticle =  $this->referenceArticleRepository->find($data['referenceArticle']);
            $reception = $receptionReferenceArticle->getReception();

            $receptionReferenceArticle
                ->setLabel($data['libelle'])
                ->setAnomalie($data['anomalie'])
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refArticle)
                ->setQuantite($data['quantite'])
                ->setQuantiteAR($data['quantiteAR'])
                ->setCommentaire($data['commentaire']);

            if (array_key_exists('articleFournisseur', $data) && $data['articleFournisseur']) {
                $articleFournisseur = $this->articleFournisseurRepository->find($data['articleFournisseur']);
                $receptionReferenceArticle
                    ->setArticleFournisseur($articleFournisseur);
            }

            $em =  $this->getDoctrine()->getManager();
            $em->flush();


            $nbArticleNotConform =  $this->receptionReferenceArticleRepository->countNotConformByReception($reception);
            $statutLabel =  $nbArticleNotConform > 0 ? Reception::STATUT_ANOMALIE : Reception::STATUT_RECEPTION_PARTIELLE;
            $statut =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, $statutLabel);
            $reception->setStatut($statut);

            $em->flush();
            $json = [
                'entete' =>  $this->renderView('reception/enteteReception.html.twig', ['reception' =>  $reception])
            ];
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/voir/{id}", name="reception_show", methods={"GET", "POST"})
     */
    public function show(Reception $reception, $id): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $type = $reception->getType();
        // dump($type);
        if ($type) {
            $valeurChampLibreTab = $this->valeurChampsLibreRepository->getByReceptionAndType($reception->getId(), $type);
        } else {
            $valeurChampLibreTab = [];
        }


        $data = [
            'valeurChampLibreTab' => $valeurChampLibreTab
        ];


        $type = $this->typeRepository->getIdAndLabelByCategoryLabel(Reception::CATEGORIE);

        $typeChampLibre =  [];
        foreach ($type as $label) {
            $champsLibresComplet = $this->champsLibreRepository->findByTypeId($label['id']);
            $champsLibres = [];
            //création array edit pour vue
            foreach ($champsLibresComplet as $champLibre) {
                $valeurChampReception = $this->valeurChampsLibreRepository->findOneByReceptionANDChampsLibre($reception->getId(), $champLibre);
                $champsLibres[] = [
                    'id' => $champLibre->getId(),
                    'label' => $champLibre->getLabel(),
                    'typage' => $champLibre->getTypage(),
                    'elements' => ($champLibre->getElements() ? $champLibre->getElements() : ''),
                    'defaultValue' => $champLibre->getDefaultValue(),
                    'valeurChampLibre' => $valeurChampReception,
                    // 'valeurChampLibreTab' => $valeurChampLibreTab,
                ];
            }
            $typeChampLibre = [
                'typeLabel' =>  $label['label'],
                'typeId' => $label['id'],
                'champsLibres' => $champsLibres,
            ];
        }

        return  $this->render("reception/show.html.twig", [
            'reception' =>  $reception,
            'id' => $id,
            'type' =>  $this->typeRepository->findOneByCategoryLabel(Reception::CATEGORIE),
            'modifiable' => ($reception->getStatut()->getNom() !== (Reception::STATUT_RECEPTION_TOTALE)),
            'statuts' =>  $this->statutRepository->findByCategorieName(Reception::CATEGORIE),
            'valeurChampsLibre' => isset($data['valeurChampLibre']) ? $data['valeurChampLibre'] : null,
            'typeChampsLibres' => $typeChampLibre,
            'champsLibres' => $champsLibres,
            'typeId' => $reception->getType()->getId(),
            'valeurChampLibreTab' => $valeurChampLibreTab,

        ]);
    }

    /**
     * @Route("/finir/{id}", name="reception_finish", methods={"GET", "POST"})
     */
    public function finish(Reception $reception): Response
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::CREATE_EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        $statut =  $this->statutRepository->findOneByCategorieAndStatut(Reception::CATEGORIE, Reception::STATUT_RECEPTION_TOTALE);
        $listReceptionReferenceArticle = $this->receptionReferenceArticleRepository->getByReception($reception);
        $em = $this->getDoctrine()->getManager();
        foreach ($listReceptionReferenceArticle as $receptionRA) {
            /** @var ReceptionReferenceArticle $receptionRA */
            $referenceArticle = $receptionRA->getReferenceArticle();
            if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $referenceArticle->setQuantiteStock($referenceArticle->getQuantiteStock() + $receptionRA->getQuantite());
            }
        }
        $reception->setStatut($statut);
        $reception->setDateReception(new \DateTime('now'));
        $em->flush();

        return  $this->redirectToRoute('reception_index');
    }

    /**
     * @Route("/article-stock", name="get_article_stock", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleStock(Request  $request)
    {
        if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        $id =  $request->request->get('id');
        $quantiteStock =  $this->referenceArticleRepository->getQuantiteStockById($id);

        return new JsonResponse($quantiteStock);
    }

    /**
     * @Route("/article-fournisseur", name="get_article_fournisseur", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getArticleFournisseur(Request  $request)
    {
        if (!$request->isXmlHttpRequest() &&  $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::RECEPTION, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $json = null;

            $refArticle = $this->referenceArticleRepository->find($data['referenceArticle']);

            if ($refArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_ARTICLE) {
                $fournisseur = $this->fournisseurRepository->find($data['fournisseur']);
                $articlesFournisseurs = $this->articleFournisseurRepository->getByRefArticleAndFournisseur($refArticle, $fournisseur);
                if ($articlesFournisseurs !== null) {
                    $json = [
                        "option" => $this->renderView(
                            'reception/optionArticleFournisseur.html.twig',
                            [
                                'articlesFournisseurs' =>  $articlesFournisseurs,
                            ]
                        )
                    ];
                }
            }
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/articlesRefs", name="get_article_refs", options={"expose"=true}, methods={"GET", "POST"})     
     */
    public function getAllReferences(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {

            $data = [];
            $data['refs'] = [];
            $reception = $this->receptionRepository->find($dataContent['reception']);
            $dimension = $this->dimensionsEtiquettesRepository->getOneDimension();
            if ($dimension) {
                $data['height'] = $dimension->getHeight();
                $data['width'] = $dimension->getWidth();
                $data['exists'] = true;
            } else {
                $data['exists'] = false;
            }
            foreach ($this->receptionReferenceArticleRepository->getByReception($reception) as $recepRef) {
                if ($recepRef->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                    array_push($data['refs'], $recepRef->getReferenceArticle()->getReference());
                } else {
                    foreach ($this->articleFournisseurRepository->getByRefArticle($recepRef->getReferenceArticle()) as $af) {
                        foreach ($this->articleRepository->getByAF($af) as $article) {
                            if ($article->getReception() && $article->getReception() === $reception) {
                                array_push($data['refs'], $article->getReference());
                            }
                        }
                    }
                }
            }
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/obtenir-ligne", name="get_ligne_from_id", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function getLignes(Request $request)
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            if ($this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']))->getReferenceArticle()->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
                $data = [];
                $data['ligneRef'] = $this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']))->getReferenceArticle()->getReference();
                $dimension = $this->dimensionsEtiquettesRepository->getOneDimension();
                if ($dimension) {
                    $data['height'] = $dimension->getHeight();
                    $data['width'] = $dimension->getWidth();
                    $data['exists'] = true;
                } else {
                    $data['exists'] = false;
                }
                return new JsonResponse($data);
            } else {
                $data = [];
                $data['article'] = $this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']))->getReferenceArticle()->getReference();
                return new JsonResponse($data);
            }
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/ajouter_lot", name="add_lot", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function addLot(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse($this->renderView('reception/modalConditionnementRow.html.twig'));
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/valider_lot", name="validate_lot", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function validateLot(Request $request)
    {
        if ($request->isXmlHttpRequest() && $dataContent = json_decode($request->getContent(), true)) {
            $em = $this->getDoctrine()->getManager();
            $response = [];
            $response['refs'] = [];
            $dimension = $this->dimensionsEtiquettesRepository->getOneDimension();
            if ($dimension) {
                $response['height'] = $dimension->getHeight();
                $response['width'] = $dimension->getWidth();
                $response['exists'] = true;
            } else {
                $response['exists'] = false;
            }
            $qtt = 0;
            for ($i = 0; $i < count($dataContent['quantiteLot']); $i++) {
                for ($j = 0; $j < $dataContent['quantiteLot'][$i]; $j++) {
                    $qtt += $dataContent['tailleLot'][$i];
                }
            }
            $ligne = $this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']));
            if ($qtt + $ligne->getQuantite() > $ligne->getQuantiteAR()) {
                $response['exists'] = false;
            } else {
                $ligne->setQuantite($ligne->getQuantite() + $qtt);
            }
            $counter = 0;
            if ($response['exists'] === true) {
                for ($i = 0; $i < count($dataContent['quantiteLot']); $i++) {
                    for ($j = 0; $j < $dataContent['quantiteLot'][$i]; $j++) {
                        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                        $ref = $date->format('YmdHis');
                        $refArticle = $this->referenceArticleRepository->getByReference($dataContent['refArticle']);
                        $toInsert = new Article();
                        $statut = $this->statutRepository->findOneByCategorieAndStatut(Article::CATEGORIE, Article::STATUT_ACTIF);
                        $ligne = $this->receptionReferenceArticleRepository->find(intval($dataContent['ligne']));
                        $articleFournisseur = new ArticleFournisseur();
                        $articleFournisseur
                            ->setReferenceArticle($refArticle)
                            ->setFournisseur($ligne->getFournisseur())
                            ->setReference($refArticle->getReference())
                            ->setLabel($ligne->getLabel());
                        $em->persist($articleFournisseur);
                        $toInsert
                            ->setLabel($ligne->getLabel())
                            ->setConform(true)
                            ->setStatut($statut)
                            ->setReference($ref . '-' . $counter)
                            ->setQuantite(intval($dataContent['tailleLot'][$i]))
                            ->setArticleFournisseur($articleFournisseur)
                            ->setReception($ligne->getReception())
                            ->setType($refArticle->getType());
                        $em->persist($toInsert);
                        array_push($response['refs'], $toInsert->getReference());
                        $counter++;
                    }
                }
            }
            $em->flush();
            return new JsonResponse($response);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/apiArticle", name="article_by_reception_api", options={"expose"=true}, methods="GET|POST")
     */
    public function apiArticle(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $ligne = $request->request->get('ligne')) {
            if (!$this->userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $ligne = $this->receptionReferenceArticleRepository->find(intval($ligne));
            $data = $this->articleDataService->getDataForDatatableByReceptionLigne($ligne);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }
}
