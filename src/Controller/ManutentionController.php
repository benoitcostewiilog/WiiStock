<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Manutention;
use App\Repository\ParamClientRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ManutentionRepository;
use App\Repository\StatutRepository;
use App\Service\MailerService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/manutention")
 */
class ManutentionController extends AbstractController
{
    /**
     * @var ManutentionRepository
     */
    private $manutentionRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

	/**
	 * @var ParamClientRepository
	 */
    private $paramClientRepository;

    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var MailerService
     */
    private $mailerService;


    public function __construct(ParamClientRepository $paramClientRepository, ManutentionRepository $manutentionRepository, EmplacementRepository $emplacementRepository, StatutRepository $statutRepository, UtilisateurRepository $utilisateurRepository, UserService $userService, MailerService $mailerService)
    {
        $this->manutentionRepository = $manutentionRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->statutRepository = $statutRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->userService = $userService;
        $this->mailerService = $mailerService;
        $this->paramClientRepository = $paramClientRepository;
    }

    /**
     * @Route("/api", name="manutention_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }

            $manutentions = $this->manutentionRepository->findAll();

            $rows = [];
            foreach ($manutentions as $manutention) {

                $rows[] = [
                    'id' => ($manutention->getId() ? $manutention->getId() : 'Non défini'),
                    'Date demande' => ($manutention->getDate() ? $manutention->getDate()->format('d/m/Y') : null),
                    'Demandeur' => ($manutention->getDemandeur() ? $manutention->getDemandeur()->getUserName() : null),
                    'Libellé' => ($manutention->getlibelle() ? $manutention->getLibelle() : null),
                    'Date souhaitée' => ($manutention->getDateAttendue() ? $manutention->getDateAttendue()->format('d/m/Y H:i') : null),
                    'Statut' => ($manutention->getStatut()->getNom() ? $manutention->getStatut()->getNom() : null),
                    'Actions' => $this->renderView('manutention/datatableManutentionRow.html.twig', [
                        'manut' => $manutention
                    ]),
                ];
            }
            $data['data'] = $rows;

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/liste/{filter}", name="manutention_index", options={"expose"=true}, methods={"GET", "POST"})
	 * @param string|null $filter
	 * @return Response
     */
    public function index($filter = null): Response
    {
        if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

		switch ($filter) {
			case 'a-traiter':
				$filter = Manutention::STATUT_A_TRAITER;
				break;
		}

        return $this->render('manutention/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
            'statuts' => $this->statutRepository->findByCategorieName(Manutention::CATEGORIE),
			'filterStatus' => $filter
		]);
    }

    /**
     * @Route("/voir", name="manutention_show", options={"expose"=true}, methods="GET|POST")
     */
    public function show(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::MANUT, Action::LIST)) {
				return $this->redirectToRoute('access_denied');
			}

            $manutention = $this->manutentionRepository->find($data);
            $json = $this->renderView('manutention/modalShowManutentionContent.html.twig', [
                'manut' => $manutention,
            ]);
            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }


    /**
     * @Route("/creer", name="manutention_new", options={"expose"=true}, methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::CREATE)) {
                return $this->redirectToRoute('access_denied');
            }

            $status = $this->statutRepository->findOneByCategorieAndStatut(Manutention::CATEGORIE, Manutention::STATUT_A_TRAITER);
            $manutention = new Manutention();
            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));

            $manutention
                ->setDate($date)
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setStatut($status)
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
				->setDateAttendue($data['date-attendue'] ? new \DateTime($data['date-attendue']) : null)
				->setCommentaire($data['commentaire']);

            $em = $this->getDoctrine()->getEntityManager();
            $em->persist($manutention);

            $em->flush();

            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/api-modifier", name="manutention_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $manutention = $this->manutentionRepository->find($data['id']);
            $json = $this->renderView('manutention/modalEditManutentionContent.html.twig', [
                'manut' => $manutention,
                'utilisateurs' => $this->utilisateurRepository->findAll(),
                'emplacements' => $this->emplacementRepository->findAll(),
                'statut' => (($manutention->getStatut()->getNom() === Manutention::STATUT_A_TRAITER) ? 1 : 0),
                'statuts' => $this->statutRepository->findByCategorieName(Manutention::CATEGORIE),
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="manutention_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $manutention = $this->manutentionRepository->find($data['id']);
            $statutLabel = (intval($data['statut']) === 1) ? Manutention::STATUT_A_TRAITER : Manutention::STATUT_TRAITE;
            $statut = $this->statutRepository->findOneByCategorieAndStatut(Manutention::CATEGORIE, $statutLabel);
            $manutention->setStatut($statut);
            $manutention
                ->setLibelle(substr($data['Libelle'], 0, 64))
                ->setSource($data['source'])
                ->setDestination($data['destination'])
                ->setDemandeur($this->utilisateurRepository->find($data['demandeur']))
				->setDateAttendue($data['date-attendue'] ? new \DateTime($data['date-attendue']) : null)
				->setCommentaire($data['commentaire']);
            $em = $this->getDoctrine()->getManager();
            $em->flush();

            if ($statutLabel == Manutention::STATUT_TRAITE) {
                $this->mailerService->sendMail(
                    'FOLLOW GT // Manutention effectuée',
                    $this->renderView('mails/mailManutentionDone.html.twig', [
                    	'manut' => $manutention,
						'title' => 'Votre demande de manutention a bien été effectuée.',
					]),
                    $manutention->getDemandeur()->getEmail()
                );
            }

            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="manutention_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
				return $this->redirectToRoute('access_denied');
			}

            $manutention = $this->manutentionRepository->find($data['manutention']);

            if ($manutention->getStatut()->getNom() == Manutention::STATUT_A_TRAITER) {
				$entityManager = $this->getDoctrine()->getManager();
				$entityManager->remove($manutention);
				$entityManager->flush();
				$response = true;
            } else {
            	$response = false;
			}
            //TODO gérer retour message erreur

            return new JsonResponse($response);
        }

        throw new NotFoundHttpException("404");
    }
}