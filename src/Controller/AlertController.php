<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @Route("/alerte")
 */
class AlertController extends AbstractController
{

    /**
     * @var object|string
     */
    private $user;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->user = $tokenStorage->getToken()->getUser();
    }

    /**
     * @Route("/liste", name="alerte_index", methods="GET|POST", options={"expose"=true})
     * @param UserService $userService
     * @return Response
     */
    public function indexAlerte(UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('alerte_reference/index.html.twig', [
            'types' => [
                [
                    'label' => ReferenceArticle::TYPE_QUANTITE_REFERENCE
                ],
                [
                    'label' => ReferenceArticle::TYPE_QUANTITE_ARTICLE
                ]
            ]
        ]);
    }

    /**
     * @Route("/api", name="alerte_ref_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param RefArticleDataService $refArticleDataService
     * @param UserService $userService
     * @return Response
     */
    public function api(Request $request,
                        RefArticleDataService $refArticleDataService,
                        UserService $userService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::STOCK, Action::LIST)) {
                return $this->redirectToRoute('access_denied');
            }
            $data = $refArticleDataService->getAlerteDataByParams($request->request, $this->getUser());
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
