<?php

namespace App\Controller\IOT;

use App\Service\IOT\MessageService;
use App\Service\UserService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class IOTController
 * @package App\Controller
 * @Route("/devices")
 */
class DeviceController extends AbstractFOSRestController
{
    /**
     * @Route("/", name="devices_index")
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
    public function index(UserService $userService)
    {
        return $this->render('IOT/devices_index.html.twig');
    }

    /**
     * @Route("/api", name="devices_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param MessageService $messageService
     * @return Response
     */
    public function api(Request $request, MessageService $messageService): Response
    {
        $data = $messageService->getDataForDatatable($request->request);
        return new JsonResponse($data);
    }
}
