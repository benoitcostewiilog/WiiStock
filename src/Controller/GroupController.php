<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategoryType;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;

use App\Entity\TrackingMovement;
use App\Entity\Type;
use App\Helper\FormatHelper;
use App\Service\CSVExportService;
use App\Service\GroupService;
use App\Service\PackService;
use App\Service\TrackingMovementService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @Route("/groupes")
 */
class GroupController extends AbstractController {

    /**
     * @Route("/api", name="group_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::TRACA, Action::DISPLAY_PACK}, mode=HasPermission::IN_JSON)
     */
    public function api(Request $request, GroupService $groupService): Response {
        return $this->json($groupService->getDataForDatatable($request->request));
    }

    /**
     * @Route("/csv", name="print_csv_packs", options={"expose"=true}, methods={"GET"})
     * @HasPermission({Menu::TRACA, Action::EXPORT})
     */
    public function printCSVPacks(Request $request,
                                  CSVExportService $CSVExportService,
                                  TrackingMovementService $trackingMovementService,
                                  TranslatorInterface $translator,
                                  EntityManagerInterface $entityManager): Response {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {

            $csvHeader = [
                'Numéro colis',
                $translator->trans('natures.Nature de colis'),
                'Date du dernier mouvement',
                'Issu de',
                'Issu de (numéro)',
                'Emplacement',
            ];

            return $CSVExportService->streamResponse(
                function($output) use ($CSVExportService, $translator, $entityManager, $dateTimeMin, $dateTimeMax, $trackingMovementService) {
                    $packRepository = $entityManager->getRepository(Pack::class);
                    $packs = $packRepository->getByDates($dateTimeMin, $dateTimeMax);
                    $trackingMouvementRepository = $entityManager->getRepository(TrackingMovement::class);

                    foreach ($packs as $pack) {
                        $trackingMouvment = $trackingMouvementRepository->find($pack['fromTo']);
                        $mvtData = $trackingMovementService->getFromColumnData($trackingMouvment);
                        $pack['fromLabel'] = $translator->trans($mvtData['fromLabel']);
                        $pack['fromTo'] = $mvtData['from'];
                        $this->putPackLine($output, $CSVExportService, $pack);
                    }
                }, 'export_colis.csv',
                $csvHeader
            );
        }
    }

    /**
     * @Route("/{packCode}", name="get_pack_intel", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @param string $packCode
     * @return JsonResponse
     */
    public function getPackIntel(EntityManagerInterface $entityManager,
                                 string $packCode): JsonResponse {
        $packRepository = $entityManager->getRepository(Pack::class);
        $naturesRepository = $entityManager->getRepository(Nature::class);
        $natures = $naturesRepository->findBy([], ['label' => 'ASC']);
        $uniqueNature = count($natures) === 1;
        $pack = $packRepository->findOneBy(['code' => $packCode]);

        if ($pack && $pack->getNature()) {
            $nature = [
                'id' => $pack->getNature()->getId(),
                'label' => $pack->getNature()->getLabel(),
            ];
        } else {
            $nature = ($uniqueNature ? [
                'id' => $natures[0]->getId(),
                'label' => $natures[0]->getLabel(),
            ] : null);
        }

        return new JsonResponse([
            'success' => true,
            'pack' => [
                'code' => $packCode,
                'quantity' => $pack ? $pack->getQuantity() : null,
                'comment' => $pack ? $pack->getComment() : null,
                'weight' => $pack ? $pack->getWeight() : null,
                'volume' => $pack ? $pack->getVolume() : null,
                'nature' => $nature
            ]
        ]);
    }

    /**
     * @Route("/api-modifier", name="pack_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager,
                            UserService $userService): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                $packRepository = $entityManager->getRepository(Pack::class);
                $natureRepository = $entityManager->getRepository(Nature::class);
                $pack = $packRepository->find($data['id']);
                $html = $this->renderView('pack/modalEditPackContent.html.twig', [
                    'natures' => $natureRepository->findBy([], ['label' => 'ASC']),
                    'pack' => $pack
                ]);
            } else {
                $html = '';
            }

            return new JsonResponse($html);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="pack_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @param PackService $packService
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         UserService $userService,
                         PackService $packService,
                         TranslatorInterface $translator): Response {
        if (!$userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }
        $data = json_decode($request->getContent(), true);
        $response = [];
        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $pack = $packRepository->find($data['id']);
        $packDataIsValid = $packService->checkPackDataBeforeEdition($data);
        if (!empty($pack) && $packDataIsValid['success']) {
            $packService
                ->editPack($data, $natureRepository, $pack);

            $entityManager->flush();
            $response = [
                'success' => true,
                'msg' => $translator->trans('colis.Le colis {numéro} a bien été modifié', [
                        "{numéro}" => '<strong>' . $pack->getCode() . '</strong>'
                    ]) . '.'
            ];
        } else if (!$packDataIsValid['success']) {
            $response = $packDataIsValid;
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="pack_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @param TranslatorInterface $translator
     * @param Arrivage $arrivage
     * @return Response
     */
    public function delete(Request $request,
                           EntityManagerInterface $entityManager,
                           UserService $userService,
                           TranslatorInterface $translator): Response {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $packRepository = $entityManager->getRepository(Pack::class);
            $arrivageRepository = $entityManager->getRepository(Arrivage::class);

            $pack = $packRepository->find($data['pack']);
            $packCode = $pack->getCode();
            $arrivage = isset($data['arrivage']) ? $arrivageRepository->find($data['arrivage']) : null;
            if (!$pack->getTrackingMovements()->isEmpty()) {
                $msg = $translator->trans("colis.Ce colis est référencé dans un ou plusieurs mouvements de traçabilité");
            }

            if (!$pack->getDispatchPacks()->isEmpty()) {
                $msg = $translator->trans("colis.Ce colis est référencé dans un ou plusieurs acheminements");
            }

            if (!$pack->getLitiges()->isEmpty()) {
                $msg = $translator->trans("colis.Ce colis est référencé dans un ou plusieurs litiges");
            }
            if ($pack->getArrivage() && $arrivage !== $pack->getArrivage()) {
                $msg = $translator->trans('colis.Ce colis est utilisé dans l\'arrivage {arrivage}', [
                    "{arrivage}" => $pack->getArrivage()->getNumeroArrivage()
                ]);
            }

            if (isset($msg)) {
                return $this->json([
                    "success" => false,
                    "msg" => $msg
                ]);
            }

            $entityManager->remove($pack);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans('colis.Le colis {numéro} a bien été supprimé', [
                        "{numéro}" => '<strong>' . $packCode . '</strong>'
                    ]) . '.'
            ]);
        }

        throw new BadRequestHttpException();
    }

    private function putPackLine($handle, CSVExportService $csvService, array $pack) {
        $line = [
            $pack['code'],
            $pack['nature'],
            FormatHelper::datetime($pack['lastMvtDate']),
            $pack['fromLabel'],
            $pack['fromTo'],
            $pack['location']
        ];
        $csvService->putLine($handle, $line);
    }

}
