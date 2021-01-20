<?php

namespace App\Controller;

use App\Entity\IOT\Message;
use App\Repository\IOT\MessageRepository;
use App\Service\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;


/**
 * Class IOTController
 * @package App\Controller
 */
class IOTController extends AbstractFOSRestController
{

    const API_KEY = "VHaP4XuNxxZtxUZCK4TtWQwmLpbxc9eejrkPDsNe8bJrCWEwmTMZSqP5yTf5LLFB";
    const PROFILE_TO_ALERT = [
        'ineo-sens-acs' => 'Température'
    ];

    /**
     * @Rest\Post("/iot")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function postApiKey(Request $request, EntityManagerInterface $entityManager) {
        if ($request->headers->get('x-api-key') === self::API_KEY) {
            $message = $request->request->get('message');
            if (isset(self::PROFILE_TO_ALERT[$message['profile']])) {
                $received = new Message();
                $received
                    ->setConfig($message)
                    ->setDevice($message['device_id'] ?? -1);
                $entityManager->persist($received);
                $entityManager->flush();
            }
            return new JsonResponse('OK');
        } else {
            dump('Invalid api key provided.');
            return new JsonResponse('NOK', 500);
        }
    }
}
