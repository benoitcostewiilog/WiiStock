<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\Menu;

use App\Repository\ArrivageRepository;
use App\Repository\ChauffeurRepository;
use App\Repository\FournisseurRepository;
use App\Repository\LitigeRepository;
use App\Repository\StatutRepository;
use App\Repository\TransporteurRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;

use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/litige")
 */
class LitigeController extends AbstractController
{
	/**
	 * @var UtilisateurRepository
	 */
	private $utilisateurRepository;
	/**
	 * @var StatutRepository
	 */
	private $statutRepository;
	/**
	 * @var FournisseurRepository
	 */
	private $fournisseurRepository;
	/**
	 * @var TransporteurRepository
	 */
	private $transporteurRepository;
	/**
	 * @var ChauffeurRepository
	 */
	private $chauffeurRepository;
	/**
	 * @var TypeRepository
	 */
	private $typeRepository;
	/**
	 * @var LitigeRepository
	 */
	private $litigeRepository;
	/**
	 * @var ArrivageRepository
	 */
	private $arrivageRepository;
	/**
	 * @var UserService
	 */
	private $userService;

	/**
	 * @param ArrivageRepository $arrivageRepository
	 * @param LitigeRepository $litigeRepository
	 * @param UtilisateurRepository $utilisateurRepository
	 * @param StatutRepository $statutRepository
	 * @param FournisseurRepository $fournisseurRepository
	 * @param TransporteurRepository $transporteurRepository
	 * @param ChauffeurRepository $chauffeurRepository
	 * @param TypeRepository $typeRepository
	 * @param UserService $userService;
	 */
	public function __construct(UserService $userService, ArrivageRepository $arrivageRepository, LitigeRepository $litigeRepository, UtilisateurRepository $utilisateurRepository, StatutRepository $statutRepository, FournisseurRepository $fournisseurRepository, TransporteurRepository $transporteurRepository, ChauffeurRepository $chauffeurRepository, TypeRepository $typeRepository)
	{
		$this->utilisateurRepository = $utilisateurRepository;
		$this->statutRepository = $statutRepository;
		$this->fournisseurRepository = $fournisseurRepository;
		$this->transporteurRepository = $transporteurRepository;
		$this->chauffeurRepository = $chauffeurRepository;
		$this->typeRepository = $typeRepository;
		$this->litigeRepository = $litigeRepository;
		$this->arrivageRepository = $arrivageRepository;
		$this->userService = $userService;
	}

	/**
	 * @Route("/arrivage/liste", name="litige_arrivage_index", options={"expose"=true}, methods="GET|POST")
	 * @return Response
	 */
    public function index()
    {
        return $this->render('litige/index_arrivages.html.twig',[
			'utilisateurs' => $this->utilisateurRepository->findAllSorted(),
            'statuts' => $this->statutRepository->findByCategorieName(CategorieStatut::LITIGE_ARR),
            'providers' => $this->fournisseurRepository->findAllSorted(),
            'carriers' => $this->transporteurRepository->findAllSorted(),
            'drivers' => $this->chauffeurRepository->findAllSorted(),
            'types' => $this->typeRepository->findByCategoryLabel(CategoryType::LITIGE)
		]);
    }

	/**
	 * @Route("/arrivage/api", name="litige_arrivage_api", options={"expose"=true}, methods="GET|POST")
	 */
    public function apiArrivage(Request $request) {
		if ($request->isXmlHttpRequest()) {
			if (!$this->userService->hasRightFunction(Menu::LITIGE, Action::LIST)) {
				return $this->redirectToRoute('access_denied');
			}

			$litiges = $this->litigeRepository->getAllWithArrivageData();

			$rows = [];
			foreach ($litiges as $litige) {
				$arrivage = $this->arrivageRepository->find($litige['arrivageId']);
				$acheteursUsernames = [];
				foreach ($arrivage->getAcheteurs() as $acheteur) {
					$acheteursUsernames[] = $acheteur->getUsername();
				}

				$lastHistoric = $this->litigeRepository->getLastHistoricByLitigeId($litige['id']);
				$lastHistoricStr = $lastHistoric ? $lastHistoric['date']->format('d/m/Y H:i') . ' : ' . strip_tags($lastHistoric['comment']) : '';

				$rows[] = [
					'type' => $litige['type'] ?? '',
					'arrivalNumber' => $litige['numeroReception'] ?? '',
					'buyers' => implode(', ', $acheteursUsernames),
					'provider' => $litige['provider'] ?? '',
					'carrier' => $litige['carrier'] ?? '',
					'lastHistoric' => $lastHistoricStr,
					'status' => $litige['status'] ?? '',
					'creationDate' => $litige['creationDate'] ? $litige['creationDate']->format('d/m/Y') : '',
					'updateDate' => $litige['updateDate'] ? $litige['updateDate']->format('d/m/Y') : '',
					'actions' => $this->renderView('litige/datatableLitigesArrivageRow.html.twig', [
						'litigeId' => $litige['id']
					])
				];
			}

			$data['data'] = $rows;

			return new JsonResponse($data);
		}
		throw new NotFoundHttpException('404');
	}
}
