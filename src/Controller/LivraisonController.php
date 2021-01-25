<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Livraison;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\NegativeQuantityException;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\LivraisonService;
use App\Service\LivraisonsManagerService;
use App\Service\PreparationsManagerService;
use App\Service\UserService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * @Route("/livraison")
 */
class LivraisonController extends AbstractController
{
    /**
     * @Route("/liste/{demandId}", name="livraison_index", methods={"GET", "POST"})
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @param string|null $demandId
     * @return Response
     */
    public function index(UserService $userService,
                          EntityManagerInterface $entityManager,
                          string $demandId = null): Response {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $statutRepository = $entityManager->getRepository(Statut::class);
        $typeRepository = $entityManager->getRepository(Type::class);
        $demandeRepository = $entityManager->getRepository(Demande::class);

        $filterDemand = $demandId
            ? $demandeRepository->find($demandId)
            : null;

        return $this->render('livraison/index.html.twig', [
            'filterDemandId' => isset($filterDemand) ? $demandId : null,
            'filterDemandValue' => isset($filterDemand) ? $filterDemand->getNumero() : null,
            'filtersDisabled' => isset($filterDemand),
            'displayDemandFilter' => true,
            'statuts' => $statutRepository->findByCategorieName(CategorieStatut::ORDRE_LIVRAISON),
            'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
        ]);
    }

    /**
     * @Route(
     *     "/finir/{id}",
     *     name="livraison_finish",
     *     options={"expose"=true},
     *     methods={"POST"},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @param Livraison $livraison
     * @param LivraisonsManagerService $livraisonsManager
     * @param UserService $userService
     * @param EntityManagerInterface $entityManager
     * @return Response
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     * @throws Exception
     */
    public function finish(Livraison $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           UserService $userService,
                           EntityManagerInterface $entityManager): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }

        if ($livraison->getStatut()->getnom() === Livraison::STATUT_A_TRAITER) {
            try {
                $dateEnd = new DateTime('now', new DateTimeZone('Europe/Paris'));
                /** @var Utilisateur $user */
                $user = $this->getUser();
                $livraisonsManager->finishLivraison(
                    $user,
                    $livraison,
                    $dateEnd,
                    $livraison->getDemande()->getDestination()
                );
                $entityManager->flush();
            }
            catch(NegativeQuantityException $exception) {
                $barcode = $exception->getArticle()->getBarCode();
                return new JsonResponse([
                    'success' => false,
                    'message' => "La quantité en stock de l'article $barcode est inférieure à la quantité prélevée."
                ]);
            }
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('livraison_show', [
                'id' => $livraison->getId()
            ])
        ]);
    }

    /**
     * @Route("/api", name="livraison_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param LivraisonService $livraisonService
     * @param UserService $userService
     * @return Response
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function api(Request $request,
                        LivraisonService $livraisonService,
                        UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
                return $this->redirectToRoute('access_denied');
            }

            $filterDemandId = $request->request->get('filterDemand');
            $data = $livraisonService->getDataForDatatable($request->request, $filterDemandId);
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api-article/{id}", name="livraison_article_api", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param UserService $userService
     * @param Livraison $livraison
     * @return Response
     */
    public function apiArticle(Request $request,
                               UserService $userService,
                               Livraison $livraison): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
                return $this->redirectToRoute('access_denied');
            }

            $preparation = $livraison->getPreparation();
            $data = [];
            if ($preparation) {
                $rows = [];
                foreach ($preparation->getArticles() as $article) {
                    if ($article->getQuantite() !== 0 && $article->getQuantitePrelevee() !== 0) {
                        $rows[] = [
                            "Référence" => $article->getArticleFournisseur()->getReferenceArticle() ? $article->getArticleFournisseur()->getReferenceArticle()->getReference() : '',
                            "Libellé" => $article->getLabel() ? $article->getLabel() : '',
                            "Emplacement" => $article->getEmplacement() ? $article->getEmplacement()->getLabel() : '',
                            "Quantité" => $article->getQuantitePrelevee(),
                            "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                'id' => $article->getId(),
                            ])
                        ];
                    }
                }

                foreach ($preparation->getLigneArticlePreparations() as $ligne) {
                    if ($ligne->getQuantitePrelevee() > 0) {
                        $rows[] = [
                            "Référence" => $ligne->getReference()->getReference(),
                            "Libellé" => $ligne->getReference()->getLibelle(),
                            "Emplacement" => $ligne->getReference()->getEmplacement() ? $ligne->getReference()->getEmplacement()->getLabel() : '',
                            "Quantité" => $ligne->getQuantitePrelevee(),
                            "Actions" => $this->renderView('livraison/datatableLivraisonListeRow.html.twig', [
                                'refArticleId' => $ligne->getReference()->getId(),
                            ])
                        ];
                    }
                }

                $data['data'] = $rows;
            } else {
                $data = false; //TODO gérer retour message erreur
            }
            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/voir/{id}", name="livraison_show", methods={"GET","POST"})
     * @param Livraison $livraison
     * @param UserService $userService
     * @return Response
     */
    public function show(Livraison $livraison,
                         UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DISPLAY_ORDRE_LIVR)) {
            return $this->redirectToRoute('access_denied');
        }

        $demande = $livraison->getDemande();

        $utilisateurPreparation = $livraison->getPreparation() ? $livraison->getPreparation()->getUtilisateur() : null;
        $demandeur = $demande ? $demande->getUtilisateur() : null;
        $destination = $demande ? $demande->getDestination() : null;
        $dateLivraison = $livraison->getDateFin();
        $comment = $demande->getCommentaire();

        return $this->render('livraison/show.html.twig', [
            'demande' => $demande,
            'livraison' => $livraison,
            'preparation' => $livraison->getPreparation(),
            'finished' => $livraison->isCompleted(),
            'headerConfig' => [
                [ 'label' => 'Numéro', 'value' => $livraison->getNumero() ],
                [ 'label' => 'Statut', 'value' => $livraison->getStatut() ? ucfirst($livraison->getStatut()->getNom()) : '' ],
                [ 'label' => 'Opérateur', 'value' => $utilisateurPreparation ? $utilisateurPreparation->getUsername() : '' ],
                [ 'label' => 'Demandeur', 'value' => $demandeur ? $demandeur->getUsername() : '' ],
                [ 'label' => 'Point de livraison', 'value' => $destination ? $destination->getLabel() : '' ],
                [ 'label' => 'Date de livraison', 'value' => $dateLivraison ? $dateLivraison->format('d/m/Y') : '' ],
                [
                    'label' => 'Commentaire',
                    'value' => $comment ?: '',
                    'isRaw' => true,
                    'colClass' => 'col-sm-6 col-12',
                    'isScrollable' => true,
                    'isNeededNotEmpty' => true
                ]
            ]
        ]);
    }

    /**
     * @Route("/{livraison}", name="livraison_delete", options={"expose"=true}, methods={"DELETE"}, condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param Livraison $livraison
     * @param LivraisonsManagerService $livraisonsManager
     * @param PreparationsManagerService $preparationsManager
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return Response
     * @throws NonUniqueResultException
     */
    public function delete(Request $request,
                           Livraison $livraison,
                           LivraisonsManagerService $livraisonsManager,
                           PreparationsManagerService $preparationsManager,
                           EntityManagerInterface $entityManager,
                           UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::ORDRE, Action::DELETE)) {
            $data = [
                'success' => true,
                'redirect' => $this->generateUrl('access_denied'),
            ];
        }
        else {
            $emplacementRepository = $entityManager->getRepository(Emplacement::class);
            $preparation = $livraison->getpreparation();

            /** @var Utilisateur $user */
            $user = $this->getUser();

            $livraisonStatus = $livraison->getStatut();
            $demande = $livraison->getDemande();

            $articleDestinationId = $request->request->get('dropLocation');
            $articlesDestination = !empty($articleDestinationId) ? $emplacementRepository->find($articleDestinationId) : null;
            if (empty($articlesDestination)) {
                $articlesDestination = isset($demande) ? $demande->getDestination() : null;
            }

            if (isset($livraisonStatus) &&
                isset($articlesDestination)) {
                $livraisonsManager->resetStockMovementsOnDelete(
                    $livraison,
                    $articlesDestination,
                    $user,
                    $entityManager
                );
            }

            $preparationsManager->resetPreparationToTreat($preparation, $entityManager);

            $entityManager->flush();

            $preparation->setLivraison(null);
            $entityManager->remove($livraison);
            $entityManager->flush();

            $data = [
                'success' => true,
                'redirect' => $this->generateUrl('preparation_show', [
                    'id' => $preparation->getId()
                ]),
            ];
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/csv", name="get_delivery_order_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param EntityManagerInterface $entityManager
     * @param LivraisonService $livraisonService
     * @return Response
     */
    public function getDeliveryOrderCSV(Request $request,
                                        CSVExportService $CSVExportService,
                                        EntityManagerInterface $entityManager,
                                        LivraisonService $livraisonService): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }
        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                'numéro',
                'statut',
                'date création',
                'date de livraison',
                'date de la demande',
                'demandeur',
                'opérateur',
                'type',
                'commentaire',
                'référence',
                'libellé',
                'emplacement',
                'quantité à livrer',
                'quantité en stock',
                'code-barre'
            ];

            return $CSVExportService->streamResponse(
                function ($output) use ($entityManager, $dateTimeMin, $dateTimeMax, $CSVExportService, $livraisonService) {
                    $livraisonRepository = $entityManager->getRepository(Livraison::class);
                    $deliveryIterator = $livraisonRepository->iterateByDates($dateTimeMin, $dateTimeMax);

                    foreach ($deliveryIterator as $delivery) {
                        $livraisonService->putLivraisonLine($output, $CSVExportService, $delivery);
                    }
                }, 'export_Ordres_Livraison.csv',
                $csvHeader
            );
        }
        else {
            throw new NotFoundHttpException('404');
        }
    }
}
