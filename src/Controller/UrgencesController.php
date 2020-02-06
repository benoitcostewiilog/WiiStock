<?php

namespace App\Controller;


use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Urgence;
use App\Repository\UrgenceRepository;
use App\Repository\UtilisateurRepository;
use App\Service\UrgenceService;
use App\Service\UserService;
use DateTime;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/urgences")
 */
class UrgencesController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var UrgenceRepository
     */
    private $urgenceRepository;

    /**
     * @var UrgenceService
     */
    private $urgenceService;

    public function __construct(UserService $userService, UrgenceRepository $urgenceRepository, UrgenceService $urgenceService)
    {
        $this->userService = $userService;
        $this->urgenceRepository = $urgenceRepository;
        $this->urgenceService = $urgenceService;
    }

    /**
     * @Route("/", name="urgence_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_URGE)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('urgence/index.html.twig', [

        ]);
    }

    /**
     * @Route("/api", name="urgence_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_URGE)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $this->urgenceService->getDataForDatatable($request->request);
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="urgence_new", options={"expose"=true}, methods={"GET", "POST"})
     * @param Request $request
     * @param UtilisateurRepository $utilisateurRepository
     * @return Response
     */
    public function new(Request $request,
                        UtilisateurRepository $utilisateurRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $dateStart = DateTime::createFromFormat('d/m/Y H:i', $data['dateStart'], new DateTimeZone("Europe/Paris"));
            $dateEnd = DateTime::createFromFormat('d/m/Y H:i', $data['dateEnd'], new DateTimeZone("Europe/Paris"));
            $urgence = new Urgence();
            $urgence
                ->setCommande($data['commande'])
                ->setDateStart($dateStart)
                ->setDateEnd($dateEnd);

            if (isset($data['acheteur'])) {
                $buyer = $utilisateurRepository->find($data['acheteur']);
                if (isset($buyer)) {
                    $urgence->setBuyer($buyer);
                }
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($urgence);

            $em->flush();
            return new JsonResponse($data);
        }
        throw new XmlHttpException('404 not found');
    }

    /**
     * @Route("/supprimer", name="urgence_delete", options={"expose"=true},methods={"GET","POST"})
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

            $urgence = $this->urgenceRepository->find($data['urgence']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($urgence);
            $entityManager->flush();
            return new JsonResponse();
        }

        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="urgence_edit_api", options={"expose"=true}, methods="GET|POST")
     */
    public function editApi(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::ARRIVAGE, Action::CREATE_EDIT)) {
                return $this->redirectToRoute('access_denied');
            }
            $urgence = $this->urgenceRepository->find($data['id']);
            $json = $this->renderView('urgence/modalEditUrgenceContent.html.twig', [
                'urgence' => $urgence,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="urgence_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UtilisateurRepository $utilisateurRepository
     * @return Response
     */
    public function edit(Request $request,
                         UtilisateurRepository $utilisateurRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::MANUT, Action::EDIT_DELETE)) {
                return $this->redirectToRoute('access_denied');
            }

			$dateStart = DateTime::createFromFormat('d/m/Y H:i', $data['dateStart'], new DateTimeZone("Europe/Paris"));
			$dateEnd = DateTime::createFromFormat('d/m/Y H:i', $data['dateEnd'], new DateTimeZone("Europe/Paris"));
            $urgence = $this->urgenceRepository->find($data['id']);
            $urgence
                ->setCommande($data['commande'])
                ->setDateStart($dateStart)
                ->setDateEnd($dateEnd);

            $buyer = isset($data['acheteur'])
                ? $utilisateurRepository->find($data['acheteur'])
                : null;

            if(isset($buyer)) {
                $buyer->addEmergency($urgence);
            }
            else {
                $urgence->setBuyer(null);
            }

            $em = $this->getDoctrine()->getManager();
            $em->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}
