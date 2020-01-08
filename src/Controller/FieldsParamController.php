<?php


namespace App\Controller;

use App\Entity\Menu;
use App\Repository\FieldsParamRepository;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/champs-fixes")
 */
class FieldsParamController extends AbstractController
{

    /**
     * @Route("/", name="fields_param_index")
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
    public function index(UserService $userService)
    {
        if (!$userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        return $this->render('fields_param/index.html.twig', []);
    }

    /**
     * @Route("/api", name="fields_param_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param FieldsParamRepository $fieldsParamRepository
     * @return Response
     */
    public function api(Request $request,
                        UserService $userService,
                        FieldsParamRepository $fieldsParamRepository): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $arrayFields = $fieldsParamRepository->findAll();
            $rows = [];
            foreach ($arrayFields as $field) {
                $url['edit'] = $this->generateUrl('fields_api_edit', ['id' => $field->getId()]);

                $rows[] =
                    [
                        'entityCode' => $field->getEntityCode(),
                        'fieldCode' => $field->getFieldCode(),
                        'Actions' => $this->renderView('fields_param/datatableFieldsRow.html.twig', [
                            'url' => $url,
                            'fieldId' => $field->getId(),
                        ]),
                    ];
            }
            $data['data'] = $rows;
            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/api-modifier", name="fields_api_edit", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param FieldsParamRepository $fieldsParamRepository
     * @return Response
     */
    public function apiEdit(Request $request,
                            UserService $userService,
                            FieldsParamRepository $fieldsParamRepository): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $field = $fieldsParamRepository->find($data['id']);

            $json = $this->renderView('fields_param/modalEditFieldsContent.html.twig', [
                'field' => $field,
            ]);

            return new JsonResponse($json);
        }
        throw new NotFoundHttpException("404");
    }

    /**
     * @Route("/modifier", name="fields_edit",  options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param FieldsParamRepository $fieldsParamRepository
     * @return Response
     */
    public function edit(Request $request,
                         UserService $userService,
                         FieldsParamRepository $fieldsParamRepository): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $entityManager = $this->getDoctrine()->getManager();
            $field = $fieldsParamRepository->find($data['field']);
            $fieldName = $field->getFieldCode();
            $fieldEntity = $field->getEntityCode();

            $field
                ->setMustToCreate($data['mustToCreate'])
                ->setMustToModify($data['mustToModify']);

            $entityManager->persist($field);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le champs fixe "' . $fieldName . '" dans "' . $fieldEntity . '" a bien été modifié.'
            ]);
        }
        throw new NotFoundHttpException('404');
    }
}
