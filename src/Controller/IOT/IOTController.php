<?php

namespace App\Controller\IOT;

use App\Service\IOT\IOTService;
use App\Service\IOT\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


/**
 * Class IOTController
 * @package App\Controller
 */
class IOTController extends AbstractFOSRestController
{

    /**
     * @Rest\Post("/iot")
     * @Rest\View()
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param MessageService $messageService
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function postApiKey(Request $request,
                               EntityManagerInterface $entityManager,
                               MessageService $messageService) {
        if ($request->headers->get('x-api-key') === $_SERVER['APP_IOT_API_KEY']) {
            $message = $request->request->get('message');
            $messageService->onMessageReceived($message, $entityManager);
            return new Response();
        } else {
            throw new BadRequestHttpException();
        }
    }
}
