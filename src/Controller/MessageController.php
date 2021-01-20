<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\Import;
use App\Entity\IOT\Message;
use App\Entity\Menu;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use App\Repository\IOT\MessageRepository;
use App\Service\ImportService;
use App\Service\IOTService;
use App\Service\MessageService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Date;
use Exception;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations as Rest;
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
