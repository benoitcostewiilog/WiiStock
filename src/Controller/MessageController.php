<?php

namespace App\Controller;

use App\Service\MessageService;
use App\Service\UserService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * Class IOTController
 * @package App\Controller
 * @Route("/messages")
 */
class MessageController extends AbstractFOSRestController
{
    /**
     * @Route("/", name="messages_index")
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
    public function index(UserService $userService)
    {
        return $this->render('IOT/messages_index.html.twig');
    }

    /**
     * @Route("/api", name="messages_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param MessageService $messageService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function api(Request $request, MessageService $messageService): Response
    {
        $data = $messageService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }
}
