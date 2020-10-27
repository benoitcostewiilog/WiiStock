<?php

namespace App\Controller;

use App\Service\IOTService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;


/**
 * Class IOTController
 * @package App\Controller
 * @Rest\Prefix("/iot")
 */
class IOTController extends AbstractFOSRestController implements ClassResourceInterface
{
    private const TREAT_MESSAGE = 1;

    /**
     * @Rest\Post("/messages")
     * @Rest\View()
     * @param IOTService $IOTService
     * @param Request $request
     * @return View
     */
    public function postMessage(IOTService $IOTService,
                                Request $request): View {
        $messageType = self::TREAT_MESSAGE; // TODO get messageType from $request ?
        switch ($messageType) {
            case self::TREAT_MESSAGE:
                $data = $IOTService->treatMessage();
                break;
            default:
                $data = [
                    'success' => false,
                    'message' => 'Unsupported message type.'
                ];
                $statusCode = Response::HTTP_BAD_REQUEST;
                break;
        }

        return View::create(
            $data ?? ['success' => true],
            $statusCode ?? Response::HTTP_OK
        );
    }
}
