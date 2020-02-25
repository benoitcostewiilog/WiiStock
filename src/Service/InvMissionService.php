<?php


namespace App\Service;

use App\Entity\Article;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
use App\Repository\FiltreSupRepository;
use App\Repository\InventoryEntryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ArticleRepository;
use App\Repository\InventoryMissionRepository;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Environment as Twig_Environment;

class InvMissionService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

	/**
	 * @var FiltreSupRepository
	 */
    private $filtreSupRepository;

	/**
	 * @var InventoryEntryRepository
	 */
    private $inventoryEntryRepository;

	/**
	 * @var Security
	 */
    private $security;

    private $em;

    public function __construct(RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                InventoryMissionRepository $inventoryMissionRepository,
								InventoryEntryRepository $inventoryEntryRepository,
								Security $security,
								FiltreSupRepository $filtreSupRepository)
    {
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->security = $security;
        $this->inventoryEntryRepository = $inventoryEntryRepository;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getDataForMissionsDatatable($params = null)
	{
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_MISSIONS, $this->security->getUser());

		$queryResult = $this->inventoryMissionRepository->findMissionsByParamsAndFilters($params, $filters);

		$missions = $queryResult['data'];

		$rows = [];
		foreach ($missions as $mission) {
			$rows[] = $this->dataRowMission($mission);
		}

		return [
			'data' => $rows,
			'recordsFiltered' => $queryResult['count'],
			'recordsTotal' => $queryResult['total'],
		];
	}

	/**
	 * @param InventoryMission $mission
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
	public function dataRowMission($mission)
	{
		$nbArtInMission = $this->articleRepository->countByMission($mission);
		$nbRefInMission = $this->referenceArticleRepository->countByMission($mission);
		$nbEntriesInMission = $this->inventoryEntryRepository->countByMission($mission);

		$rateBar = (($nbArtInMission + $nbRefInMission) != 0)
            ? ($nbEntriesInMission * 100 / ($nbArtInMission + $nbRefInMission))
            : 0;

		$row =
			[
				'StartDate' => $mission->getStartPrevDate() ? $mission->getStartPrevDate()->format('d/m/Y') : '',
				'EndDate' => $mission->getEndPrevDate() ? $mission->getEndPrevDate()->format('d/m/Y') : '',
				'Anomaly' => $this->inventoryMissionRepository->countAnomaliesByMission($mission) > 0,
				'Rate' => $this->templating->render('inventaire/datatableMissionsBar.html.twig', [
					'rateBar' => $rateBar
				]),
				'Actions' => $this->templating->render('inventaire/datatableMissionsRow.html.twig', [
					'missionId' => $mission->getId(),
				]),
			];
		return $row;
	}

    public function getDataForOneMissionDatatable($mission, $params = null)
    {
		$filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_INV_SHOW_MISSION, $this->security->getUser());

		$queryResultRef = $this->inventoryMissionRepository->findRefByMissionAndParamsAndFilters($mission, $params, $filters);
        $queryResultArt = $this->inventoryMissionRepository->findArtByMissionAndParamsAndFilters($mission, $params, $filters);

        $refArray = $queryResultRef['data'];
        $artArray = $queryResultArt['data'];

        $rows = [];
        foreach ($refArray as $ref) {
            $rows[] = $this->dataRowRefMission($ref, $mission);
        }
        foreach ($artArray as $art) {
            $rows[] = $this->dataRowArtMission($art, $mission);
        }
        $index = intval($params->get('order')[0]['column']);
        if ($rows) {
        	$columnName = array_keys($rows[0])[$index];
        	$column = array_column($rows, $columnName);
        	array_multisort($column, $params->get('order')[0]['dir'] === "asc" ? SORT_ASC : SORT_DESC, $rows);
		}
        return [
            'data' => $rows,
            'recordsTotal' => $queryResultRef['total'] + $queryResultArt['total'],
            'recordsFiltered' => $queryResultRef['count'] + $queryResultArt['count'],
        ];
    }

	/**
	 * @param ReferenceArticle $ref
	 * @param InventoryMission $mission
	 * @return array
	 * @throws NonUniqueResultException
	 */
    public function dataRowRefMission($ref, $mission)
    {
        $refDate = $this->referenceArticleRepository->getEntryDateByMission($mission, $ref);

        return $this->dataRowMissionArtRef(
            $ref->getEmplacement(),
            $ref->getReference(),
            $ref->getLibelle(),
            (!empty($refDate) && isset($refDate['date'])) ? $refDate['date'] : null,
            $this->referenceArticleRepository->countInventoryAnomaliesByRef($ref) > 0 ? 'oui' : ($refDate ? 'non' : '-')
        );
    }

	/**
	 * @param Article $art
	 * @param InventoryMission $mission
	 * @return array
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
    public function dataRowArtMission($art, $mission) {
        $artDate = $this->articleRepository->getEntryDateByMission($mission, $art);
        return $this->dataRowMissionArtRef(
            $art->getEmplacement(),
            $art->getReference(),
            $art->getlabel(),
            !empty($artDate) ? $artDate['date'] : null,
            $this->articleRepository->countInventoryAnomaliesByArt($art) > 0 ? 'oui' : ($artDate ? 'non' : '-')
        );
    }

    /**
     * @param Emplacement|null $emplacement
     * @param string|null $reference
     * @param string|null $label
     * @param DateTimeInterface|null $date
     * @param string|null $anomaly
     * @return array
     */
    private function dataRowMissionArtRef(?Emplacement $emplacement,
                                          ?string $reference,
                                          ?string $label,
                                          ?DateTimeInterface $date,
                                          ?string $anomaly): array {
        if ($emplacement) {
            $location = $emplacement->getLabel();
            $emptyLocation = false;
        } else {
            $location = '<i class="fas fa-exclamation-triangle red" title="Aucun emplacement défini : n\'apparaîtra sur le nomade."></i>';
            $emptyLocation = true;
        }

        return [
            'Ref' => $reference,
            'Label' => $label,
            'Location' => $location,
            'Date' => isset($date) ? $date->format('d/m/Y') : '',
            'Anomaly' => $anomaly,
            'EmptyLocation' => $emptyLocation
        ];
    }
}
