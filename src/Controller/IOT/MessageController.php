<?php

namespace App\Controller\IOT;

use App\Entity\IOT\Device;
use App\Service\IOT\MessageService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class IOTController
 * @package App\Controller
 */
class MessageController extends AbstractFOSRestController
{
    /**
     * @Route("/devices/{device}/messages", name="device_message_index")
     * @param Device $device
     * @return RedirectResponse|Response
     */
    public function index(Device $device)
    {
        return $this->render('IOT/message/messages_index.html.twig', [
            'device' => $device->getId()
        ]);
    }

    /**
     * @Route("/api", name="device_messages_api", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param MessageService $messageService
     * @return Response
     */
    public function api(Request $request, MessageService $messageService): Response
    {
        $data = $messageService->getDataForDatatableFromDevice($request->request);
        return new JsonResponse($data);
    }
}
