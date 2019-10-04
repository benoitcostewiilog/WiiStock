<?php


namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;

use App\Entity\MouvementStock;
use App\Repository\InventoryMissionRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;

use App\Service\InventoryService;
use App\Service\InventoryServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Service\UserService;


/**
 * @Route("/inventaire/anomalie")
 */
class InventoryAnomalyController extends AbstractController
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

    /**
     * @var InventoryEntryRepository
     */
    private $inventoryEntryRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

	/**
	 * @var InventoryService
	 */
    private $inventoryService;

    public function __construct(InventoryService $inventoryService, UserService $userService, InventoryMissionRepository $inventoryMissionRepository, InventoryEntryRepository $inventoryEntryRepository, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
    {
        $this->userService = $userService;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->inventoryService = $inventoryService;
    }

	/**
	 * @Route("/", name="show_anomalies")
	 */
    public function showAnomalies()
	{
		if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER)) {
			return $this->redirectToRoute('access_denied');
		}

		return $this->render('inventaire/anomalies.html.twig');
	}

	/**
	 * @Route("/api", name="inv_anomalies_api", options={"expose"=true}, methods="GET|POST")
	 */
	public function apiAnomalies(Request $request)
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

            $refAnomalies = $this->inventoryMissionRepository->getInventoryRefAnomalies();
            $artAnomalies = $this->inventoryMissionRepository->getInventoryArtAnomalies();

            $anomalies = array_merge($refAnomalies, $artAnomalies);

			$rows = [];
			foreach ($anomalies as $anomaly) {
				$rows[] =
					[
						'reference' => $anomaly['reference'],
						'libelle' => $anomaly['label'],
						'quantite' => $anomaly['quantity'],
						'Actions' => $this->renderView('inventaire/datatableAnomaliesRow.html.twig',
							[
								'reference' => $anomaly['reference'],
								'isRef' => $anomaly['is_ref'],
								'quantity' => $anomaly['quantity'],
								'location' => $anomaly['location'],
							]),
					];
			}
			$data['data'] = $rows;
			return new JsonResponse($data);
		}
		throw new NotFoundHttpException("404");
	}

	/**
	 * @Route("/traitement", name="anomaly_treat", options={"expose"=true}, methods="GET|POST")
	 */
	public function treatAnomaly(Request $request)
	{
		if ($request->isXmlHttpRequest()  && $data = json_decode($request->getContent(), true)) {
			if (!$this->userService->hasRightFunction(Menu::INVENTAIRE, Action::INVENTORY_MANAGER)) {
				return $this->redirectToRoute('access_denied');
			}

			dump($data);
			$this->inventoryService->doTreatAnomaly($data['reference'], $data['isRef'], (int)$data['newQuantity'], $data['choice'], $data['comment']);

			return new JsonResponse($data['choice'] == 'confirm');
		}
		throw new NotFoundHttpException("404");
	}

}