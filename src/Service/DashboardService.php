<?php


namespace App\Service;


use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use App\Entity\Dashboard;
use App\Entity\LocationCluster;
use App\Entity\LocationClusterMeter;
use App\Entity\Pack;
use App\Entity\Dashboard\Meter as DashboardMeter;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\LatePack;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\ReceptionTraca;
use App\Entity\Transporteur;
use App\Entity\Urgence;
use App\Entity\WorkFreeDay;
use App\Entity\Wiilock;
use App\Helper\Stream;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Throwable;


class DashboardService
{
    public const DASHBOARD_PACKAGING = 'packaging';
    public const DASHBOARD_ADMIN = 'admin';
    public const DASHBOARD_DOCK = 'dock';

    private $enCoursService;
    private $entityManager;
    private $wiilockService;

    private $cacheDaysWorked;

    public function __construct(EnCoursService $enCoursService,
                                WiilockService $wiilockService,
                                EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->enCoursService = $enCoursService;
        $this->wiilockService = $wiilockService;
    }

    public function getWeekAssoc($firstDay, $lastDay, $beforeAfter)
    {
        $receptionTracaRepository = $this->entityManager->getRepository(ReceptionTraca::class);

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
        $secondInADay = 60 * 60 * 24;

        $keyFormat = 'd';
        $counterKeyToString = function ($key) {
            return ' ' . $key . ' ';
        };

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = $counterKeyToString(date($keyFormat, $firstDayTime + ($secondInADay * $dayIncrement)));
            $rows[$dayCounterKey] = 0;
        }

        $receptionTracas = $receptionTracaRepository->countByDays($firstDay, $lastDay);
        foreach ($receptionTracas as $qttPerDay) {
            $dayCounterKey = $counterKeyToString($qttPerDay['date']->format($keyFormat));
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
        $arrivalHistoryRepository = $this->entityManager->getRepository(ArrivalHistory::class);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

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
        $keyFormat = 'd';
        $counterKeyToString = function ($key) {
            return ' ' . $key . ' ';
        };

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = $counterKeyToString(date( $keyFormat, $firstDayTime + ($secondInADay * $dayIncrement)));
            $rows[$dayCounterKey] = [
                'count' => 0,
                'conform' => null
            ];
        }

        $arrivages = $arrivageRepository->countByDays($firstDay, $lastDay);
        foreach ($arrivages as $qttPerDay) {
            $dayCounterKey = $counterKeyToString($qttPerDay['date']->format($keyFormat));
            if (!isset($rows[$dayCounterKey])) {
                $rows[$dayCounterKey] = ['count' => 0];
            }

            $rows[$dayCounterKey]['count'] += $qttPerDay['count'];

            $dateHistory = $qttPerDay['date']->setTime(0, 0);

            $arrivalHistory = $arrivalHistoryRepository->getByDate($dateHistory);

            $rows[$dayCounterKey]['conform'] = isset($arrivalHistory)
                ? $arrivalHistory->getConformRate()
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
     */
    public function getSimplifiedDataForDockDashboard()
    {
        $locationCounter = [];
        $adminData = $this->getMeterData(self::DASHBOARD_DOCK);
        foreach ($adminData as $adminDatum) {
            $locationCounter[$adminDatum['meterKey']] = $adminDatum;
        }
        return $locationCounter;
    }

    /**
     * @return array
     * @deprecated
     */
    public function getDataForReceptionDockDashboard()
    {
        $locationCounter = $this->getLocationCounters([
            'enCoursDock' => ParametrageGlobal::DASHBOARD_LOCATION_DOCK,
            'enCoursClearance' => ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK,
            'enCoursCleared' => ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE,
            'enCoursDropzone' => ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES
        ]);

        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);
        return array_merge(
            $locationCounter,
            ['dailyUrgenceCount' => $urgenceRepository->countUnsolved(true)],
            ['urgenceCount' => $urgenceRepository->countUnsolved()]
        );
    }

    /**
     * @return array
     */
    public function getSimplifiedDataForAdminDashboard()
    {
        $locationCounter = [];
        $adminData = $this->getMeterData(self::DASHBOARD_ADMIN);
        foreach ($adminData as $adminDatum) {
            $locationCounter[$adminDatum['meterKey']] = $adminDatum;
        }
        return $locationCounter;
    }

    /**
     * @return array
     * @deprecated
     */
    public function getDataForReceptionAdminDashboard()
    {
        $locationCounter = $this->getLocationCounters([
            'enCoursUrgence' => ParametrageGlobal::DASHBOARD_LOCATION_URGENCES,
            'enCoursLitige' => ParametrageGlobal::DASHBOARD_LOCATION_LITIGES,
            'enCoursClearance' => ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN
        ]);

        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);

        return array_merge(
            $locationCounter,
            ['urgenceCount' => $urgenceRepository->countUnsolved()]
        );
    }


    /**
     * @param EntityManagerInterface $entityManager
     * @return array
     * @throws NonUniqueResultException
     */
    public function getSimplifiedDataForPackagingDashboard(EntityManagerInterface $entityManager)
    {
        $locationCounter = [];
        $adminData = $this->getMeterData(self::DASHBOARD_PACKAGING);
        foreach ($adminData as $adminDatum) {
            $locationCounter[$adminDatum['meterKey']] = $adminDatum;
        }
        $chartData = $this->getChartData($entityManager, self::DASHBOARD_PACKAGING, 'of');
        return [
            'counters' => $locationCounter,
            'chartData' => $chartData['data'] ?? [],
            'chartColors' => $chartData['chartColors'] ?? [],
        ];
    }

    public function flatArray(array $toFlat): array
    {
        $formattedArrivalData = [];
        foreach ($toFlat as $datum) {
            $firstKey = array_key_first($datum);
            $formattedArrivalData[$firstKey] = $datum[$firstKey];
        }
        return $formattedArrivalData;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getDataForMonitoringPackagingDashboard()
    {
        $defaultDelay = '24:00';
        $urgenceDelay = '2:00';
        return $this->getLocationCounters([
            'packaging1' => [ParametrageGlobal::DASHBOARD_PACKAGING_1, $defaultDelay],
            'packaging2' => [ParametrageGlobal::DASHBOARD_PACKAGING_2, $defaultDelay],
            'packaging3' => [ParametrageGlobal::DASHBOARD_PACKAGING_3, $defaultDelay],
            'packaging4' => [ParametrageGlobal::DASHBOARD_PACKAGING_4, $defaultDelay],
            'packaging5' => [ParametrageGlobal::DASHBOARD_PACKAGING_5, $defaultDelay],
            'packaging6' => [ParametrageGlobal::DASHBOARD_PACKAGING_6, $defaultDelay],
            'packaging7' => [ParametrageGlobal::DASHBOARD_PACKAGING_7, $defaultDelay],
            'packaging8' => [ParametrageGlobal::DASHBOARD_PACKAGING_8, $defaultDelay],
            'packaging9' => [ParametrageGlobal::DASHBOARD_PACKAGING_9, $defaultDelay],
            'packaging10' => [ParametrageGlobal::DASHBOARD_PACKAGING_10, $defaultDelay],
            'packagingKitting' => [ParametrageGlobal::DASHBOARD_PACKAGING_KITTING, $defaultDelay],
            'packagingRPA' => [ParametrageGlobal::DASHBOARD_PACKAGING_RPA, $defaultDelay],
            'packagingLitige' => [ParametrageGlobal::DASHBOARD_PACKAGING_LITIGE, $defaultDelay],
            'packagingUrgence' => [ParametrageGlobal::DASHBOARD_PACKAGING_URGENCE, $urgenceDelay]
        ]);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    public function getAndSetGraphDataForDock(EntityManagerInterface $entityManager)
    {
        $this->parseColisData($entityManager);
        $this->parseDailyArrivalData($entityManager);
        $this->parseWeeklyArrivalData($entityManager);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    private function parseDailyArrivalData(EntityManagerInterface $entityManager)
    {
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $packRepository = $entityManager->getRepository(Pack::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);

        $workFreeDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
        $arrivalCountByDays = $this->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        }, $workFreeDays);

        $colisCountByDay = $this->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($packRepository) {
            return $packRepository->countByDates($dateMin, $dateMax);
        }, $workFreeDays);
        $colisCountByDaySaved = $this->saveArrayForEncoding($colisCountByDay);
        $arrivalCountByDaySaved = $this->saveArrayForEncoding($arrivalCountByDays);
        $dashboardColisData = [
            'data' => $colisCountByDaySaved,
            'chartColors' => [],
            'dashboard' => self::DASHBOARD_DOCK,
            'key' => 'arrivage-colis-daily',
        ];
        $dashboardArrivalData = [
            'data' => $arrivalCountByDaySaved,
            'chartColors' => [],
            'dashboard' => self::DASHBOARD_DOCK,
            'key' => 'arrivage-daily',
        ];
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardColisData);
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardArrivalData);
    }

    private function saveArrayForEncoding($arrToSave): array
    {
        $json = [];
        foreach ($arrToSave as $key => $arrToSaveItem) {
            $json[] = [$key => $arrToSaveItem];
        }
        return $json;
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    private function parseWeeklyArrivalData(EntityManagerInterface $entityManager)
    {
        $arrivageRepository = $entityManager->getRepository(Arrivage::class);
        $packRepository = $entityManager->getRepository(Pack::class);

        $arrivalsCountByWeek = $this->getWeeklyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($arrivageRepository) {
            return $arrivageRepository->countByDates($dateMin, $dateMax);
        });

        $colisCountByWeek = $this->getWeeklyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax) use ($packRepository) {
            return $packRepository->countByDates($dateMin, $dateMax);
        });
        $dashboardDataForColis = [
            'data' => $this->saveArrayForEncoding($colisCountByWeek),
            'dashboard' => self::DASHBOARD_DOCK,
            'chartColors' => [],
            'key' => 'arrivage-colis-weekly',
        ];
        $dashboardDataForArrival = [
            'data' => $this->saveArrayForEncoding($arrivalsCountByWeek),
            'dashboard' => self::DASHBOARD_DOCK,
            'chartColors' => [],
            'key' => 'arrivage-weekly',
        ];
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardDataForColis);
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardDataForArrival);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function parseColisData(EntityManagerInterface $entityManager)
    {
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);
        $locationClusterMeterRepository = $entityManager->getRepository(LocationClusterMeter::class);

        $workFreeDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();

        $packsCountByDays = $this->getDailyObjectsStatistics(function (DateTime $date) use ($locationClusterMeterRepository) {
            return $locationClusterMeterRepository->countByDate(
                $date,
                LocationCluster::CLUSTER_CODE_DOCK_DASHBOARD_DROPZONE
            );
        }, $workFreeDays);
        $dashboardData = [
            'data' => $this->saveArrayForEncoding($packsCountByDays),
            'chartColors' => [],
            'dashboard' => self::DASHBOARD_DOCK,
            'key' => 'colis'
        ];
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardData);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param array $data
     * @throws NonUniqueResultException
     */
    private function updateOrPersistDashboardGraphMeter(EntityManagerInterface $entityManager, array $data)
    {
        $dashboardChartMeterRepository = $entityManager->getRepository(DashboardMeter\Chart::class);
        $dashboardChartMeter = $dashboardChartMeterRepository->findEntityByDashboardAndId($data['dashboard'], $data['key']);
        if (!isset($dashboardChartMeter)) {
            $dashboardChartMeter = new DashboardMeter\Chart();
            $entityManager->persist($dashboardChartMeter);
        }
        $dashboardChartMeter
            ->setData($data['data'])
            ->setChartColors($data['chartColors'])
            ->setDashboard($data['dashboard'])
            ->setTotal(isset($data['total']) ? $data['total'] : null)
            ->setLocation(isset($data['location']) ? $data['location'] : null)
            ->setChartKey($data['key']);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $clusterCode
     * @throws NonUniqueResultException
     * @throws Exception
     */
    public function getAndSetGraphDataForAdmin(EntityManagerInterface $entityManager, string $clusterCode)
    {
        if (!in_array($clusterCode, [LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1, LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_2 ])) {
            throw new Exception('Cluster code in not supported');
        }

        $adminDelay = '48:00';

        $natureRepository = $entityManager->getRepository(Nature::class);
        $locationClusterRepository = $entityManager->getRepository(LocationCluster::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $workedDaysRepository = $entityManager->getRepository(DaysWorked::class);
        $packsRepository = $entityManager->getRepository(Pack::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);
        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();

        $naturesFilterParam = ($clusterCode === LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1)
            ? ParametrageGlobal::DASHBOARD_NATURE_COLIS
            : ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS;

        // on récupère les natures paramétrées
        $paramNatureForGraph = $parametrageGlobalRepository->getOneParamByLabel($naturesFilterParam);
        $naturesIdForGraph = !empty($paramNatureForGraph) ? explode(',', $paramNatureForGraph) : [];
        $naturesForGraph = !empty($naturesIdForGraph)
            ? $natureRepository->findBy(['id' => $naturesIdForGraph])
            : [];

        $locationCounters = [];

        $globalCounter = 0;

        $olderPackLocation = [
            'locationLabel' => null,
            'locationId' => null,
            'packDateTime' => null
        ];

        if (!empty($naturesForGraph)) {
            $cluster = $locationClusterRepository->findOneBy([
                'code' => $clusterCode
            ]);
            $packsOnCluster = $locationClusterRepository->getPacksOnCluster($clusterCode, $naturesForGraph);
            $packsOnClusterVerif = Stream::from(
                $packsRepository->getCurrentPackOnLocations(
                    $cluster->getLocations()->toArray(),
                    $naturesIdForGraph,
                    false,
                    'colis.id, emplacement.label'
                )
            )->reduce(function(array $carry, array $pack) {
                if (!isset($carry[$pack['label']])) {
                    $carry[$pack['label']] = [];
                }
                $carry[$pack['label']][] = $pack['id'];
                return $carry;
            }, []);
            $countByNatureBase = [];
            foreach ($naturesForGraph as $wantedNature) {
                $countByNatureBase[$wantedNature->getLabel()] = 0;
            }

            $workFreeDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
            $graphData = $this->getObjectForTimeSpan(function (int $beginSpan, int $endSpan)
                                                     use (
                                                         $workFreeDays,
                                                         $daysWorked,
                                                         $workFreeDaysRepository,
                                                         $countByNatureBase,
                                                         $naturesForGraph,
                                                         &$packsOnCluster,
                                                         $adminDelay,
                                                         $packsOnClusterVerif,
                                                         &$locationCounters,
                                                         &$olderPackLocation,
                                                         &$globalCounter) {
                $countByNature = array_merge($countByNatureBase);
                $packUntreated = [];
                foreach ($packsOnCluster as $pack) {
                    if (isset($packsOnClusterVerif[$pack['currentLocationLabel']]) && in_array(intval($pack['packId']), $packsOnClusterVerif[$pack['currentLocationLabel']])) {
                        $date = $this->enCoursService->getTrackingMovementAge($daysWorked, $pack['firstTrackingDateTime'], $workFreeDays);
                        $timeInformation = $this->enCoursService->getTimeInformation($date, $adminDelay);
                        $countDownHours = isset($timeInformation['countDownLateTimespan'])
                            ? ($timeInformation['countDownLateTimespan'] / 1000 / 60 / 60)
                            : null;

                        if (isset($countDownHours)
                            && (
                                ($countDownHours < 0 && $beginSpan === -1) // count colis en retard
                                || ($countDownHours >= 0 && $countDownHours >= $beginSpan && $countDownHours < $endSpan)
                            )) {

                            $countByNature[$pack['natureLabel']]++;

                            $currentLocationLabel = $pack['currentLocationLabel'];
                            $currentLocationId = $pack['currentLocationId'];
                            $lastTrackingDateTime = $pack['lastTrackingDateTime'];

                            // get older pack
                            if ((
                                    empty($olderPackLocation['locationLabel'])
                                    || empty($olderPackLocation['locationId'])
                                    || empty($olderPackLocation['packDateTime'])
                                )
                                || ($olderPackLocation['packDateTime'] > $lastTrackingDateTime)) {
                                $olderPackLocation['locationLabel'] = $currentLocationLabel;
                                $olderPackLocation['locationId'] = $currentLocationId;
                                $olderPackLocation['packDateTime'] = $lastTrackingDateTime;
                            }

                            // increment counters
                            if (empty($locationCounters[$currentLocationId])) {
                                $locationCounters[$currentLocationId] = 0;
                            }

                            $locationCounters[$currentLocationId]++;
                            $globalCounter++;
                        } else {
                            $packUntreated[] = $pack;
                        }
                    }
                }
                $packsOnCluster = $packUntreated;
                return $countByNature;
            });
        }

        if (empty($graphData)) {
            $graphData = $this->getObjectForTimeSpan(function () {
                return 0;
            });
        }

        $totalToDisplay = !empty($olderPackLocation['locationId'])
            ? $globalCounter
            : null;

        $locationToDisplay = !empty($olderPackLocation['locationLabel'])
            ? $olderPackLocation['locationLabel']
            : null;
        $dashboardData = [
            'dashboard' => self::DASHBOARD_ADMIN,
            'chartColors' => array_reduce(
                $naturesForGraph,
                function (array $carry, Nature $nature) {
                    $color = $nature->getColor();
                    if (!empty($color)) {
                        $carry[$nature->getLabel()] = $color;
                    }
                    return $carry;
                },
                []),
            'key' => $clusterCode,
            'data' => $graphData,
            'location' => (!empty($locationToDisplay) ? $locationToDisplay : '-'),
            'total' => (!empty($totalToDisplay) ? $totalToDisplay : '-'),
        ];
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardData);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param string $dashboard
     * @param string $id
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getChartData(EntityManagerInterface $entityManager, string $dashboard, string $id)
    {
        $dashboardChartMeterRepository = $entityManager->getRepository(DashboardMeter\Chart::class);
        return $dashboardChartMeterRepository->findByDashboardAndId($dashboard, $id);
    }

    /**
     * @param string $dashboard
     * @return array
     */
    public function getMeterData(string $dashboard): array
    {
        $dashboardMeterRepository = $this->entityManager->getRepository(DashboardMeter\Indicator::class);
        return $dashboardMeterRepository->getByDashboard($dashboard);
    }

    /**
     * @param array $counterConfig
     * @return array
     * @throws Exception
     * @deprecated
     */
    private function getLocationCounters(array $counterConfig): array
    {
        $workedDaysRepository = $this->entityManager->getRepository(DaysWorked::class);
        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        return array_reduce(
            array_keys($counterConfig),
            function (array $carry, string $key) use ($counterConfig, $daysWorked) {
                $delay = is_array($counterConfig[$key])
                    ? $counterConfig[$key][1]
                    : null;
                $param = is_array($counterConfig[$key])
                    ? $counterConfig[$key][0]
                    : $counterConfig[$key];
                $carry[$key] = $this->getDashboardCounter($param, $daysWorked, $delay);
                return $carry;
            },
            []);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param array $locationIds
     * @param array $daysWorked
     * @param bool $includeDelay
     * @param bool $includeLocationLabels
     * @return array|null
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getDashboardCounter(EntityManagerInterface $entityManager,
                                        array $locationIds,
                                        array $daysWorked = [],
                                        bool $includeDelay = false,
                                        bool $includeLocationLabels = false): ?array
    {
        $packRepository = $entityManager->getRepository(Pack::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);

        if ($includeLocationLabels && !empty($locationIds)) {
            $locationRepository = $entityManager->getRepository(Emplacement::class);
            $locations = $locationRepository->findByIds($locationIds);
        }
        else {
            $locations = [];
        }

        if (!empty($locations)) {
            $response = [];
            $response['delay'] = null;
            if ($includeDelay) {
                $lastEnCours = $packRepository->getCurrentPackOnLocations($locationIds, [], false, 'lastDrop.datetime, emplacement.dateMaxTime', 1);
                if (!empty($lastEnCours[0]) && $lastEnCours[0]['dateMaxTime']) {
                    $workFreeDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
                    $lastEnCoursDateTime = $lastEnCours[0]['datetime'];
                    $date = $this->enCoursService->getTrackingMovementAge($daysWorked, $lastEnCoursDateTime, $workFreeDays);
                    $timeInformation = $this->enCoursService->getTimeInformation($date, $lastEnCours[0]['dateMaxTime']);
                    $response['delay'] = $timeInformation['countDownLateTimespan'];
                }
            }
            $response['count'] = 0;
            $response['label'] = $includeLocationLabels
                ? array_reduce(
                    $locations,
                    function (string $carry, Emplacement $location) {
                        return $carry . (!empty($carry) ? ', ' : '') . $location->getLabel();
                    },
                    ''
                )
                : null;
            $response['count'] = $packRepository->getCurrentPackOnLocations($locationIds, []);
        } else {
            $response = null;
        }

        return $response;
    }

    /**
     * Make assoc array. Assoc a date like "d/m" to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 7 counters
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @param array $workFreeDays Days we have to ignore
     * @return array ['d/m' => integer]
     * @throws Exception
     */
    public function getDailyObjectsStatistics(callable $getCounter, array $workFreeDays = []): array
    {
        $daysWorkedRepository = $this->entityManager->getRepository(DaysWorked::class);

        $daysToReturn = [];
        $nbDaysToReturn = 7;
        $dayIndex = 0;

        $workedDaysLabels = $daysWorkedRepository->getLabelWorkedDays();

        if (!empty($workedDaysLabels)) {
            while (count($daysToReturn) < $nbDaysToReturn) {
                $dateToCheck = new DateTime("now - $dayIndex days", new DateTimeZone('Europe/Paris'));

                if (!$this->enCoursService->isDayInArray($dateToCheck, $workFreeDays)) {
                    $dateDayLabel = strtolower($dateToCheck->format('l'));

                    if (in_array($dateDayLabel, $workedDaysLabels)) {
                        $daysToReturn[] = $dateToCheck;
                    }
                }

                $dayIndex++;
            }
        }

        return array_reduce(
            array_reverse($daysToReturn),
            function (array $carry, DateTime $dateToCheck) use ($getCounter) {
                $dateMin = clone $dateToCheck;
                $dateMin->setTime(0, 0, 0);
                $dateMax = clone $dateToCheck;
                $dateMax->setTime(23, 59, 59);
                $dateToCheck->setTime(0, 0);

                $dayKey = $dateToCheck->format('d/m');
                $carry[$dayKey] = $getCounter($dateMin, $dateMax);
                return $carry;
            },
            []);
    }

    /**
     * Make assoc array. Assoc a date like ('S' . weekNumber) to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 5 counters
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array [('S' . weekNumber) => integer]
     * @throws Exception
     */
    public function getWeeklyObjectsStatistics(callable $getCounter): array
    {
        $daysWorkedRepository = $this->entityManager->getRepository(DaysWorked::class);

        $weekCountersToReturn = [];
        $nbWeeksToReturn = 5;

        $daysWorkedInWeek = $daysWorkedRepository->countDaysWorked();

        if ($daysWorkedInWeek > 0) {
            for ($weekIndex = ($nbWeeksToReturn - 2); $weekIndex >= -1; $weekIndex--) {
                $dateMin = new DateTime("monday $weekIndex weeks ago");
                $dateMin->setTime(0, 0, 0);
                $dateMax = new DateTime("sunday $weekIndex weeks ago");
                $dateMax->setTime(23, 59, 59);
                $dayKey = ('S' . $dateMin->format('W'));
                $weekCountersToReturn[$dayKey] = $getCounter($dateMin, $dateMax);
            }
        }

        return $weekCountersToReturn;
    }

    /**
     * @param callable $getObject
     * @return array
     */
    public function getObjectForTimeSpan(callable $getObject): array
    {
        $timeSpanToObject = [];
        $timeSpans = [
            -1 => -1,
            0 => 1,
            1 => 6,
            6 => 12,
            12 => 24,
            24 => 36,
            36 => 48,
        ];
        foreach ($timeSpans as $timeBegin => $timeEnd) {
            $key = $timeBegin === -1
                ? "Retard"
                : ($timeEnd === 1
                    ? "Moins d'1h"
                    : ($timeBegin . "h-" . $timeEnd . 'h'));
            $timeSpanToObject[$key] = $getObject($timeBegin, $timeEnd);
        }
        return $timeSpanToObject;
    }

    /**
     * @return array
     */
    public function getDailyArrivalCarriers(): array
    {
        $transporteurRepository = $this->entityManager->getRepository(Transporteur::class);
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $carriersParams = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_CARRIER_DOCK);
        $carriersIds = empty($carriersParams)
            ? []
            : explode(',', $carriersParams);

        return array_map(
            function ($carrier) {
                return $carrier['label'];
            },
            $transporteurRepository->getDailyArrivalCarriersLabel($carriersIds)
        );
    }


    /**
     * @param EntityManagerInterface $entityManager
     * @throws Throwable
     * @deprecated
     */
    public function retrieveAndInsertGlobalDashboardData(EntityManagerInterface $entityManager): void
    {
        $lastUpdateDate = $this->wiilockService->getLastDashboardFeedingTime($entityManager);
        $currentDate = new DateTime();
        if ($lastUpdateDate) {
            $dateDiff = $currentDate
                ->diff($lastUpdateDate);
            $hoursBetweenNowAndLastUpdateDate = $dateDiff->h + ($dateDiff->days * 24);
            if ($hoursBetweenNowAndLastUpdateDate > 2) {
                $this->wiilockService->toggleFeedingDashboard($entityManager, false);
                $entityManager->flush();
            }
        }
        if (!$this->wiilockService->dashboardIsBeingFed($entityManager)) {
            $this->wiilockService->toggleFeedingDashboard($entityManager, true);
            try {
                $this->retrieveAndInsertGlobalMeterData($entityManager);
                $this->retrieveAndInsertGlobalGraphData($entityManager);
            }
            catch (Throwable $throwable) {
                $this->wiilockService->toggleFeedingDashboard($entityManager, false);
                $this->flushAndClearEm($entityManager);
                throw $throwable;
            }

            $this->wiilockService->toggleFeedingDashboard($entityManager, false);
            $this->flushAndClearEm($entityManager);
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     */
    private function flushAndClearEm(EntityManagerInterface $entityManager)
    {
        $entityManager->flush();
        $entityManager->clear();
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     * @deprecated
     */
    private function retrieveAndInsertGlobalGraphData(EntityManagerInterface $entityManager) {
        $this->getAndSetGraphDataForDock($entityManager);
        dump('Finished Dock Graph');
        $this->flushAndClearEm($entityManager);

        $this->getAndSetGraphDataForAdmin($entityManager, LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_1);
        $this->getAndSetGraphDataForAdmin($entityManager, LocationCluster::CLUSTER_CODE_ADMIN_DASHBOARD_2);
        dump('Finished Admin Graphs');
        $this->flushAndClearEm($entityManager);

        $this->getAndSetGraphDataForPackaging($entityManager);
        dump('Finished Packaging Graph');
        $this->flushAndClearEm($entityManager);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     * @deprecated
     */
    private function retrieveAndInsertGlobalMeterData(EntityManagerInterface $entityManager) {
        $dockData = $this->getDataForReceptionDockDashboard();
        $this->parseRetrievedDataAndPersistMeter($dockData, self::DASHBOARD_DOCK, $entityManager);
        dump('Finished Dock Counter');
        $this->flushAndClearEm($entityManager);

        $adminData = $this->getDataForReceptionAdminDashboard();
        $this->parseRetrievedDataAndPersistMeter($adminData, self::DASHBOARD_ADMIN, $entityManager);
        dump('Finished Admin Counter');
        $this->flushAndClearEm($entityManager);

        $packagingData = $this->getDataForMonitoringPackagingDashboard();
        $this->parseRetrievedDataAndPersistMeter($packagingData, self::DASHBOARD_PACKAGING, $entityManager);
        dump('Finished Packaging Counter');
        $this->flushAndClearEm($entityManager);

        $this->retrieveAndInsertLastEnCours($entityManager);
        dump('Finished Late packs');
        $this->flushAndClearEm($entityManager);
    }

    /**
     * @param $data
     * @param string $dashboard
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     */
    private function parseRetrievedDataAndPersistMeter($data, string $dashboard, EntityManagerInterface $entityManager): void
    {
        $dashboardMeterRepository = $entityManager->getRepository(DashboardMeter\Indicator::class);
        foreach ($data as $key => $datum) {
            $dashboardMeter = $dashboardMeterRepository->findByKeyAndDashboard($key, $dashboard);
            if (!isset($dashboardMeter)) {
                $dashboardMeter = new DashboardMeter\Indicator();
                $entityManager->persist($dashboardMeter);
            }
            $dashboardMeter->setMeterKey($key);
            $dashboardMeter->setDashboard($dashboard);
            if (is_array($datum)) {
                $dashboardMeter
                    ->setCount($datum['count'])
                    ->setDelay($datum['delay'])
                    ->setLabel($datum['label']);
            } else {
                $dashboardMeter->setCount(intval($datum));
            }
        }
    }
    /**
     * @param EntityManagerInterface $entityManager
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function getAndSetGraphDataForPackaging(EntityManagerInterface $entityManager)
    {
        $dsqrLabel = 'OF envoyés par le DSQR';
        $gtLabel = 'OF traités par GT';
        $locationClusterMeterRepository = $this->entityManager->getRepository(LocationClusterMeter::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);

        $workFreeDays = $workFreeDaysRepository->getWorkFreeDaysToDateTime();
        $chartData = $this->getDailyObjectsStatistics(function (DateTime $date)
                                                      use ($dsqrLabel, $gtLabel, $locationClusterMeterRepository) {

            return [
                $dsqrLabel => $locationClusterMeterRepository->countByDate($date, LocationCluster::CLUSTER_CODE_PACKAGING_DSQR),
                $gtLabel => $locationClusterMeterRepository->countByDate($date, LocationCluster::CLUSTER_CODE_PACKAGING_GT_TARGET, LocationCluster::CLUSTER_CODE_PACKAGING_GT_ORIGIN),
            ];
        }, $workFreeDays);
        $dashboardData = [
            'dashboard' => self::DASHBOARD_PACKAGING,
            'chartColors' => [
                $dsqrLabel => '#003871',
                $gtLabel => '#77933C',
            ],
            'key' => 'of',
            'data' => $chartData,
        ];
        $this->updateOrPersistDashboardGraphMeter($entityManager, $dashboardData);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    public function retrieveAndInsertLastEnCours(EntityManagerInterface $entityManager)
    {
        $latePackRepository = $entityManager->getRepository(LatePack::class);
        $lastLates = $this->enCoursService->getLastEnCoursForLate();
        $latePackRepository->clearTable();
        foreach ($lastLates as $lastLate) {
            $latePack = new LatePack();
            $latePack
                ->setDelay($lastLate['delay'])
                ->setDate($lastLate['date'])
                ->setEmp($lastLate['emp'])
                ->setColis($lastLate['colis']);
            $entityManager->persist($latePack);
        }
    }

    public function getLastRefresh(string $meterType): string {
        $wiilockRepository = $this->entityManager->getRepository(Wiilock::class);
        $dashboardLock = $wiilockRepository->findOneBy(['lockKey' => $meterType]);
        return ($dashboardLock && $dashboardLock->getUpdateDate())
            ? $dashboardLock->getUpdateDate()->format('d/m/Y H:i')
            : 'Aucune données';
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param Dashboard\Component $component
     * @throws Exception
     */
    public function persistOutstandingPack(EntityManagerInterface $entityManager,
                                           Dashboard\Component $component): void {
        $config = $component->getConfig();
        $daysWorked = $this->getDaysWorked($entityManager);

        $calculatedData = $this->getDashboardCounter(
            $entityManager,
            $config['locations'],
            $daysWorked,
            (bool) $config['withTreatmentDelay'],
            (bool) $config['withLocationLabels']
        );

        /** @var DashboardMeter\Indicator|null $meter */
        $meter = $component->getMeter();

        if (!isset($meter)) {
            $meter = new DashboardMeter\Indicator();
            $meter->setComponent($component);
            $entityManager->persist($meter);
        }

        $meter
            ->setCount($calculatedData['count'])
            ->setDelay($calculatedData['delay'])
            ->setLabel($calculatedData['label'] ?? null);
    }

    private function getDaysWorked(EntityManagerInterface $entityManager): array {
        $workedDaysRepository = $entityManager->getRepository(DaysWorked::class);
        if (!isset($this->cacheDaysWorked)) {
            $this->cacheDaysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        }
        return $this->cacheDaysWorked;
    }
}
