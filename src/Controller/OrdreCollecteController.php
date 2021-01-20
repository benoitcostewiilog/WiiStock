<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Collecte;
use App\Entity\CollecteReference;
use App\Entity\Menu;
use App\Entity\OrdreCollecte;
use App\Entity\OrdreCollecteReference;

use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ArticleNotAvailableException;
use App\Service\ArticleDataService;
use App\Service\CSVExportService;
use App\Service\OrdreCollecteService;
use App\Service\PDFGeneratorService;
use App\Service\RefArticleDataService;
use App\Service\UserService;

use DateTime;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * @Route("/ordre-collecte")
 */
class OrdreCollecteController extends AbstractController
{
    /**
     * @Route("/liste/{demandId}", name="ordre_collecte_index")
     * @param string|null $demandId
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse|Response
     */
    public function index(UserService $userService, EntityManagerInterface $entityManager, string $demandId = null)
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
            return $this->redirectToRoute('access_denied');
        }
        $collecteRepository = $entityManager->getRepository(Collecte::class);
        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeCollecte = $demandId ? $collecteRepository->find($demandId) : null;

        return $this->render('ordre_collecte/index.html.twig', [
            'filterDemandId' => $demandeCollecte ? $demandId : null,
            'filterDemandValue' => $demandeCollecte ? $demandeCollecte->getNumero() : null,
            'filtersDisabled' => isset($demandeCollecte),
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_COLLECTE),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_COLLECTE]),
        ]);
    }

    /**
     * @Route("/api", name="ordre_collecte_api", options={"expose"=true})
     * @param Request $request
     * @param OrdreCollecteService $ordreCollecteService
     * @param UserService $userService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request, OrdreCollecteService $ordreCollecteService, UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
                return $this->redirectToRoute('access_denied');
            }

            // cas d'un filtre par demande de collecte
            $filterDemand = $request->request->get('filterDemand');
            $data = $ordreCollecteService->getDataForDatatable($request->request, $filterDemand);

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="ordre_collecte_show",  methods={"GET","POST"})
     * @param OrdreCollecte $ordreCollecte
     * @param OrdreCollecteService $ordreCollecteService
     * @param UserService $userService
     * @return Response
     */
    public function show(OrdreCollecte $ordreCollecte,
                         OrdreCollecteService $ordreCollecteService,
                         UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('ordre_collecte/show.html.twig', [
            'collecte' => $ordreCollecte,
            'finished' => $ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_TRAITE,
            'detailsConfig' => $ordreCollecteService->createHeaderDetailsConfig($ordreCollecte)
        ]);
    }

    /**
     * @Route("/finir/{id}", name="ordre_collecte_finish", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param OrdreCollecte $ordreCollecte
     * @param OrdreCollecteService $ordreCollecteService
     * @param UserService $userService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \Exception
     */
    public function finish(Request $request,
                           OrdreCollecte $ordreCollecte,
                           OrdreCollecteService $ordreCollecteService,
                           UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }
        $rows = $request->request->get('rows');
        if (!empty($rows) && ($ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER)) {

            $date = new DateTime('now', new DateTimeZone('Europe/Paris'));

            /** @var Utilisateur $loggedUser */
            $loggedUser = $this->getUser();

            try {
                $ordreCollecteService->finishCollecte(
                    $ordreCollecte,
                    $loggedUser,
                    $date,
                    $rows
                );

                $data = [
                    'success' => true,
                    'entete' => $this->renderView('ordre_collecte/ordre-collecte-show-header.html.twig', [
                        'collecte' => $ordreCollecte,
                        'finished' => $ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_TRAITE,
                        'showDetails' => $ordreCollecteService->createHeaderDetailsConfig($ordreCollecte)
                    ])
                ];
            }
            catch(ArticleNotAvailableException $exception) {
                $data = [
                    'success' => false,
                    'msg' => 'Une référence de la collecte n\'est pas active, vérifiez les transferts de stock en cours associés à celle-ci.'
                ];
            }
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-article/{id}", name="ordre_collecte_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param OrdreCollecte $ordreCollecte
     * @param UserService $userService
     * @return Response
     */
    public function apiArticle(Request $request, OrdreCollecte $ordreCollecte, UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_COLL)) {
                return $this->redirectToRoute('access_denied');
            }

            $rows = [];
            foreach ($ordreCollecte->getOrdreCollecteReferences() as $ligneArticle) {
                $referenceArticle = $ligneArticle->getReferenceArticle();
                $location = $referenceArticle->getEmplacement() ? $referenceArticle->getEmplacement()->getLabel() : '';
                $rows[] = [
                    "Référence" => $referenceArticle ? $referenceArticle->getReference() : ' ',
                    "Libellé" => $referenceArticle ? $referenceArticle->getLibelle() : ' ',
                    "Emplacement" => $location,
                    "Quantité" => $ligneArticle->getQuantite() ?? ' ',
                    "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                        'id' => $ligneArticle->getId(),
                        'refArticleId' => $referenceArticle->getId(),
                        'barCode' => $referenceArticle ? $referenceArticle->getBarCode() : '',
                        'quantity' => $ligneArticle->getQuantite(),
                        'modifiable' => $ordreCollecte->getStatut()
                            ? ($ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER)
                            : false,
                        'location' => $location,
                    ])
                ];
            }

            foreach ($ordreCollecte->getArticles() as $article) {
                $location = $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '';
                $rows[] = [
                    'Référence' => $article->getArticleFournisseur()
                        ? $article->getArticleFournisseur()->getReferenceArticle()->getReference()
                        : '',
                    'Libellé' => $article->getLabel(),
                    "Emplacement" => $location,
                    'Quantité' => $article->getQuantite(),
                    "Actions" => $this->renderView('ordre_collecte/datatableOrdreCollecteRow.html.twig', [
                        'id' => $article->getId(),
                        'barCode' => $article->getBarCode(),
                        'quantity' => $article->getQuantite(),
                        'modifiable' => $ordreCollecte->getStatut()
                            ? ($ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER)
                            : false,
                        'articleId' =>$article->getId(),
                        "location" => $location,
                    ])
                ];
            }

            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer/{id}", name="ordre_collecte_new", options={"expose"=true}, methods={"GET","POST"} )
     * @param Collecte $demandeCollecte
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     * @throws \Exception
     */
    public function new(Collecte $demandeCollecte,
                        UserService $userService,
                        EntityManagerInterface $entityManager): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::CREATE)) {
            return $this->redirectToRoute('access_denied');
        }
        $statutRepository = $entityManager->getRepository(Statut::class);
        // on crée l'ordre de collecte
        $statut = $statutRepository
            ->findOneByCategorieNameAndStatutCode(OrdreCollecte::CATEGORIE, OrdreCollecte::STATUT_A_TRAITER);
        $ordreCollecte = new OrdreCollecte();
        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $ordreCollecte
            ->setDate($date)
            ->setNumero('C-' . $date->format('YmdHis'))
            ->setStatut($statut)
            ->setDemandeCollecte($demandeCollecte);
        foreach ($demandeCollecte->getArticles() as $article) {
            $ordreCollecte->addArticle($article);
        }
        foreach ($demandeCollecte->getCollecteReferences() as $collecteReference) {
            $ordreCollecteReference = new OrdreCollecteReference();
            $ordreCollecteReference
                ->setOrdreCollecte($ordreCollecte)
                ->setQuantite($collecteReference->getQuantite())
                ->setReferenceArticle($collecteReference->getReferenceArticle());
            $entityManager->persist($ordreCollecteReference);
            $ordreCollecte->addOrdreCollecteReference($ordreCollecteReference);
        }

        $entityManager->persist($ordreCollecte);

        // on modifie statut + date validation de la demande
        $demandeCollecte
            ->setStatut(
            	$statutRepository->findOneByCategorieNameAndStatutCode(Collecte::CATEGORIE, Collecte::STATUT_A_TRAITER)
			)
            ->setValidationDate($date);

        try {
            $entityManager->flush();
        }
        /** @noinspection PhpRedundantCatchClauseInspection */
        catch (UniqueConstraintViolationException $e) {
            $this->addFlash('danger', 'Un autre ordre de collecte est en cours de création, veuillez réessayer.');
        }

        return $this->redirectToRoute('collecte_show', [
            'id' => $demandeCollecte->getId(),
        ]);
    }

    /**
     * @Route("/modifier-article-api", name="ordre_collecte_edit_api", options={"expose"=true}, methods={"GET","POST"} )
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function apiEditArticle(Request $request,
                                   UserService $userService,
                                   EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $ordreCollecteReferenceRepository = $entityManager->getRepository(OrdreCollecteReference::class);
            $ligneArticle = $ordreCollecteReferenceRepository->find($data['id']);
            $modif = isset($data['ref']) && !($data['ref'] === 0);

            $json = $this->renderView(
                'ordre_collecte/modalEditArticleContent.html.twig',
                [
                    'ligneArticle' => $ligneArticle,
                    'modifiable' => $modif
                ]
            );
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier-article", name="ordre_collecte_edit_article", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function editArticle(Request $request,
                                UserService $userService,
                                EntityManagerInterface $entityManager): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::STOCK, Action::EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $ordreCollecteReferenceRepository = $entityManager->getRepository(OrdreCollecteReference::class);
            $ligneArticle = $ordreCollecteReferenceRepository->find($data['ligneArticle']);
            if (isset($data['quantite'])) $ligneArticle->setQuantite(max($data['quantite'], 0)); // protection contre quantités négatives

            $entityManager->flush();

            return new JsonResponse();
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/supprimer/{id}", name="ordre_collecte_delete", options={"expose"=true}, methods={"GET","POST"})
     * @param OrdreCollecte $ordreCollecte
     * @param Request $request
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws NonUniqueResultException
     */
    public function delete(OrdreCollecte $ordreCollecte,
                           Request $request,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            if ($ordreCollecte->getStatut() && ($ordreCollecte->getStatut()->getNom() === OrdreCollecte::STATUT_A_TRAITER)) {
                $statutRepository = $entityManager->getRepository(Statut::class);
                $collecteReferenceRepository = $entityManager->getRepository(CollecteReference::class);
                $collecte = $ordreCollecte->getDemandeCollecte();
                $isOnlyOrdreCollecte = $collecte->getOrdresCollecte()->count() === 1;

                $statusName = $isOnlyOrdreCollecte ? Collecte::STATUT_BROUILLON : Collecte::STATUT_COLLECTE;
                $collecte->setStatut($statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::DEM_COLLECTE, $statusName));

                foreach ($ordreCollecte->getArticles() as $article) {
                    if (!$isOnlyOrdreCollecte) {
                        $article->removeCollecte($collecte);
                    }
                    $article->removeOrdreCollecte($ordreCollecte);
                }
                foreach ($ordreCollecte->getOrdreCollecteReferences() as $cr) {
                    if (!$isOnlyOrdreCollecte) {
                        $entityManager->remove($collecteReferenceRepository->getByCollecteAndRA($collecte, $cr->getReferenceArticle()));
                    }
                    $entityManager->remove($cr);
                }

                $collecte
                    ->setValidationDate(null);

                $entityManager->remove($ordreCollecte);
                $entityManager->flush();
                $data = [
                    'redirect' => $this->generateUrl('ordre_collecte_index'),
                ];

                return new JsonResponse($data);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'Erreur lors de la suppression de l\'ordre de collecte.',
                ]);
            }
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="get_collect_orders_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param OrdreCollecteService $ordreCollecteService
     * @param EntityManagerInterface $entityManager
     * @param CSVExportService $CSVExportService
     * @return Response
     */
    public function getCollectOrdersCSV(Request $request,
                                        OrdreCollecteService $ordreCollecteService,
                                        EntityManagerInterface $entityManager,
                                        CSVExportService $CSVExportService): Response {

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');
        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $CSVheader = [
                'numéro',
                'statut',
                'date création',
                'opérateur',
                'type',
                'référence',
                'libellé',
                'emplacement',
                'quantité à collecter',
                'code-barre',
                'destination'
            ];

            $ordreCollecteRepository = $entityManager->getRepository(OrdreCollecte::class);
            $collecteIterator = $ordreCollecteRepository->iterateByDates($dateTimeMin, $dateTimeMax);

            return $CSVExportService->streamResponse(
                function ($output) use ($collecteIterator, $CSVExportService, $ordreCollecteService) {
                    foreach ($collecteIterator as $collectOrder) {
                        $ordreCollecteService->putCollecteLine($output, $CSVExportService, $collectOrder);
                    }
                },'Export_Ordres_Collectes.csv',
                $CSVheader
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/{ordreCollecte}/etiquettes", name="collecte_bar_codes_print", options={"expose"=true})
     *
     * @param OrdreCollecte $ordreCollecte
     * @param RefArticleDataService $refArticleDataService
     * @param ArticleDataService $articleDataService
     * @param PDFGeneratorService $PDFGeneratorService
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function printCollecteBarCodes(OrdreCollecte $ordreCollecte,
                                          RefArticleDataService $refArticleDataService,
                                          ArticleDataService $articleDataService,
                                          PDFGeneratorService $PDFGeneratorService): Response
    {

        $articles = $ordreCollecte->getArticles()->toArray();
        $ordreCollecteReferences = $ordreCollecte->getOrdreCollecteReferences()->toArray();

        $barCodesArticles = array_map(function (Article $article) use ($articleDataService) {
            return $articleDataService->getBarcodeConfig($article);
        }, $articles);

        $barCodesReferences = array_map(function (OrdreCollecteReference $ordreCollecteReference) use ($refArticleDataService) {
            $referenceArticle = $ordreCollecteReference->getReferenceArticle();
            return $referenceArticle
                ? $refArticleDataService->getBarcodeConfig($referenceArticle)
                : null;
        }, $ordreCollecteReferences);

        $barCodes = array_merge($barCodesArticles, array_filter($barCodesReferences , function ($value) {
            return $value !== null;}
        ));
        $barCodesCount = count($barCodes);
        if($barCodesCount > 0) {
            $fileName = $PDFGeneratorService->getBarcodeFileName(
                $barCodes,
                'ordreCollecte'
            );

            return new PdfResponse(
                $PDFGeneratorService->generatePDFBarCodes($fileName, $barCodes),
                $fileName
            );
        } else {
            throw new NotFoundHttpException('Aucune étiquette à imprimer');
        }
    }
}
