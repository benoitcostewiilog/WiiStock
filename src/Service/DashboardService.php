<?php


namespace App\Service;


use App\Entity\Emplacement;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use App\Entity\Urgence;
use App\Repository\ArrivageRepository;
use App\Repository\ArrivalHistoryRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\ReceptionTracaRepository;
use App\Repository\UrgenceRepository;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;


class DashboardService
{

    /**
     * @var ReceptionTracaRepository
     */
    private $receptionTracaRepository;

    /**
     * @var ArrivageRepository;
     */
    private $arrivageRepository;

    /**
     * @var ArrivalHistoryRepository
     */
    private $arrivalHistoryRepository;

	/**
	 * @var ParametrageGlobalRepository
	 */
    private $parametrageGlobalRepository;

	/**
	 * @var EmplacementRepository
	 */
    private $emplacementRepository;
	/**
	 * @var EnCoursService
	 */
    private $enCoursService;
	/**
	 * @var UrgenceRepository;
	 */
    private $urgenceRepository;
    private $entityManager;

    public function __construct(EmplacementRepository $emplacementRepository,
								ParametrageGlobalRepository $parametrageGlobalRepository,
								ArrivalHistoryRepository $arrivalHistoryRepository,
								ArrivageRepository $arrivageRepository,
								ReceptionTracaRepository $receptionTracaRepository,
								EnCoursService $enCoursService,
								EntityManagerInterface $entityManager,
								UrgenceRepository $urgenceRepository
	)
    {
        $this->arrivalHistoryRepository = $arrivalHistoryRepository;
        $this->arrivageRepository = $arrivageRepository;
        $this->receptionTracaRepository = $receptionTracaRepository;
        $this->parametrageGlobalRepository = $parametrageGlobalRepository;
        $this->entityManager = $entityManager;
        $this->emplacementRepository = $emplacementRepository;
        $this->enCoursService = $enCoursService;
        $this->urgenceRepository = $urgenceRepository;
    }

    private $columnsForArrival = [
        [
            'type' => 'string',
            'value' => 'Jours'
        ],
        [
            'type' => 'number',
            'value' => 'Nombre d\'arrivages'
        ],
        [
            'type' => 'number',
            'value' => 'Taux d\'arrivages conformes'
        ],
        [
            'annotation' => true,
            'type' => 'string',
            'role' => 'tooltip'
        ]
    ];

    private $columnsForAssoc = [
        [
            'type' => 'string',
            'value' => 'Jours'
        ],
        [
            'type' => 'number',
            'value' => 'Réceptions'
        ],
    ];

    public function getWeekAssoc($firstDay, $lastDay, $beforeAfter) {
		if ($beforeAfter == 'after') {
			$firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
			$lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
		} elseif ($beforeAfter == 'before') {
			$firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
			$lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
		}
        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60*60*24;

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = date("d", $firstDayTime + ($secondInADay * $dayIncrement));
            $rows[$dayCounterKey] = 0;
        }

        foreach ($this->receptionTracaRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {
            $dayCounterKey = $qttPerDay['date']->format('d');
            $rows[$dayCounterKey] += $qttPerDay['count'];
        }

        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    public function getWeekArrival($firstDay, $lastDay, $beforeAfter)
    {
		if ($beforeAfter == 'after') {
			$firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
			$lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
		} else if ($beforeAfter == 'before') {
			$firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
			$lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
		}

        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60 * 60 * 24;

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = date("d", $firstDayTime + ($secondInADay * $dayIncrement));
            $rows[$dayCounterKey] = [
                'count' => 0,
                'conform' => null
            ];
        }

        foreach ($this->arrivageRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {

            $dayCounterKey = $qttPerDay['date']->format('d');
            if (!isset($rows[$dayCounterKey])) {
                $rows[$dayCounterKey] = ['count' => 0];
            }

            $rows[$dayCounterKey]['count'] += $qttPerDay['count'];

            $dateHistory = $qttPerDay['date']->setTime(0, 0);
            $rows[$dayCounterKey]['conform'] =
                $this->arrivalHistoryRepository->getByDate($dateHistory)
                    ? $this->arrivalHistoryRepository->getByDate($dateHistory)->getConformRate()
                    : null;
        }
        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    /**
     * @return array
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getDataForReceptionAdminDashboard() {
        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);
        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);

        $empForUrgence = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_URGENCES);
        $empForLitige = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_LITIGES);
        $empForClearance = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN);

        return [
            'enCoursUrgence' => $empForUrgence ? [
                'count' => $mouvementTracaRepository->countObjectOnLocation($empForUrgence),
                'label' => $empForUrgence->getLabel()
            ] : null,
            'enCoursLitige' => $empForLitige ? [
                'count' => $mouvementTracaRepository->countObjectOnLocation($empForLitige),
                'label' => $empForLitige->getLabel()
            ] : null,
            'enCoursClearance' => $empForClearance ? [
                'count' => $mouvementTracaRepository->countObjectOnLocation($empForClearance),
                'label' => $empForClearance->getLabel()
            ] : null,
            'urgenceCount' => $urgenceRepository->countUnsolved(),
        ];
    }

    /**
     * @return array
     * @throws DBALException
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getDataForReceptionDockDashboard() {
        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);

        $empForDock = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_DOCK);
        $empForClearance = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK);
        $empForCleared = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE);
        $empForDropZone = $this->findEmplacementParam(ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES);

		return [
			'enCoursDock' => $empForDock ? [
				'count' => $mouvementTracaRepository->countObjectOnLocation($empForDock),
				'label' => $empForDock->getLabel()
			] : null,
			'enCoursClearance' => $empForClearance ? [
				'count' => $mouvementTracaRepository->countObjectOnLocation($empForClearance),
				'label' => $empForClearance->getLabel()
			] : null,
			'enCoursCleared' => $empForCleared ? [
				'count' => $mouvementTracaRepository->countObjectOnLocation($empForCleared),
				'label' => $empForCleared->getLabel()
			] : null,
			'enCoursDropzone' => $empForDropZone ? [
				'count' => $mouvementTracaRepository->countObjectOnLocation($empForDropZone),
				'label' => $empForDropZone->getLabel()
			] : null,
			'urgenceCount' => $this->urgenceRepository->countUnsolved(),
		];
	}

	private function findEmplacementParam(string $paramName): ?Emplacement {
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);

        $param = $parametrageGlobalRepository->findOneByLabel($paramName);
        $paramValue = $param
            ? $param->getValue()
            : null;
        return $paramValue
            ? $emplacementRepository->find($paramValue)
            : null;
    }

}
