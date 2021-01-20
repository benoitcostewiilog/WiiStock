<?php

namespace App\Controller;

use App\Entity\IOT\Message;
use App\Repository\IOT\MessageRepository;
use App\Service\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Date;
use Exception;
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

    CONST INEO_SENS_ACS_TEMP = 'ineo-sens-acs';

    const API_KEY = "VHaP4XuNxxZtxUZCK4TtWQwmLpbxc9eejrkPDsNe8bJrCWEwmTMZSqP5yTf5LLFB";
    const PROFILE_TO_ALERT = [
        self::INEO_SENS_ACS_TEMP => 'Température'
    ];

    /**
     * @Rest\Post("/iot")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param IOTService $IOTService
     * @return Response
     * @throws Exception
     */
    public function postApiKey(Request $request, EntityManagerInterface $entityManager, IOTService $IOTService) {
        if ($request->headers->get('x-api-key') === self::API_KEY) {
            $message = $request->request->get('message');
            if (isset(self::PROFILE_TO_ALERT[$message['profile']])) {
                $messageDate = new \DateTime($message['timestamp'], new \DateTimeZone("UTC"));
                $messageDate->setTimezone(new \DateTimeZone('Europe/Paris'));
                $received = new Message();
                $received
                    ->setConfig($message)
                    ->setDate($messageDate)
                    ->setDevice($message['device_id'] ?? -1);
                $entityManager->persist($received);
                $entityManager->flush();
                $IOTService->treatMessage($received, $entityManager);
            }
            return new JsonResponse('OK');
        } else {
            dump('Invalid api key provided.');
            return new JsonResponse('NOK', 500);
        }
    }
}
