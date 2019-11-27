<?php


namespace App\Controller;

use App\Entity\Menu;
use App\Entity\Nature;
use App\Repository\NatureRepository;
use App\Service\UserService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/nature-colis")
 */
class NatureColisParamController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var NatureRepository
     */
    private $natureRepository;

    public function __construct(UserService $userService, NatureRepository $natureRepository)
    {
        $this->userService = $userService;
        $this->natureRepository = $natureRepository;
    }

    /**
     * @Route("/", name="nature_param_index")
     */
    public function index()
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('nature_param/index.html.twig', [
        ]);
    }

    /**
     * @Route("/api", name="nature_param_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(Request $request): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $natures = $this->natureRepository->findAll();
            $rows = [];
            foreach ($natures as $nature) {
                $url['edit'] = $this->generateUrl('nature_api_edit', ['id' => $nature->getId()]);

                $rows[] =
                    [
                        'Label' => $nature->getLabel(),
                        'Code' => $nature->getCode(),
                        'Actions' => $this->renderView('nature_param/datatableNatureRow.html.twig', [
                            'url' => $url,
                            'natureId' => $nature->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/creer", name="nature_new", options={"expose"=true}, methods="GET|POST")
     */
    public function new(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();

                $nature = new Nature();
                $nature
                    ->setLabel($data['label'])
                    ->setCode($data['code']);

                $em->persist($nature);
                $em->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La nature de colis "' . $data['label'] . '" a bien été créée.'
                ]);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="nature_api_edit", options={"expose"=true}, methods="GET|POST")
     */
    public function apiEdit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $nature = $this->natureRepository->find($data['id']);

            $json = $this->renderView('nature_param/modalEditNatureContent.html.twig', [
                'nature' => $nature,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="nature_edit",  options={"expose"=true}, methods="GET|POST")
     */
    public function edit(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $nature = $this->natureRepository->find($data['nature']);
            $natureLabel = $nature->getLabel();

                $nature
                    ->setLabel($data['label'])
                    ->setCode($data['code']);

                $em->persist($nature);
                $em->flush();

                return new JsonResponse([
                    'success' => true,
                    'msg' => 'La nature "' . $natureLabel . '" a bien été modifiée.'
                ]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/verification", name="nature_check_delete", options={"expose"=true})
     */
    public function checkStatusCanBeDeleted(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $typeId = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $natureIsUsed = $this->natureRepository->countUsedById($typeId);

            if (!$natureIsUsed) {
                $delete = true;
                $html = $this->renderView('nature_param/modalDeleteNatureRight.html.twig');
            } else {
                $delete = false;
                $html = $this->renderView('nature_param/modalDeleteNatureWrong.html.twig');
            }

            return new JsonResponse(['delete' => $delete, 'html' => $html]);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/supprimer", name="nature_delete", options={"expose"=true}, methods="GET|POST")
     */
    public function delete(Request $request): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $nature = $this->natureRepository->find($data['nature']);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($nature);
            $entityManager->flush();
            return new JsonResponse();
        }
        throw new NotFoundHttpException('404');
    }
}