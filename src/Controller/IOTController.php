<?php

namespace App\Controller;

use App\Service\IOTService;
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

    /**
     * @Rest\Post("/iot", condition="request.isXmlHttpRequest()")
     * @Rest\View()
     * @param Request $request
     * @return Response
     */
    public function postApiKey(Request $request) {
        if ($request->headers->get('x-api-key') === self::API_KEY) {
            $message = $request->request->get('message');
            dump($message);
            return new JsonResponse('OK');
        } else {
            dump('Invalid api key provided.');
            return new JsonResponse('NOK', 500);
        }
    }
}
