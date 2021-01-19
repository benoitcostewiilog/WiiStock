<?php
/**
 * Commande Cron exécutée tous les jours à 20h :
 *
 */
// 0 20 * * 1-6
namespace App\Command;

use App\Entity\AverageRequestTime;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Handling;
use App\Entity\TransferRequest;
use App\Entity\Type;
use App\Service\DateService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class AverageRequestTimeCommand extends Command
{
    protected static $defaultName = 'app:feed:average:requests';

    private $entityManager;
    private $dateService;

    public function __construct(EntityManagerInterface $entityManager, DateService $dateService)
    {
        parent::__construct(self::$defaultName);
        $this->entityManager = $entityManager;
        $this->dateService = $dateService;
    }

    protected function configure()
    {
        $this->setDescription('This command feeds the average request treating time.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $demandeRepository = $this->entityManager->getRepository(Demande::class);
        $collecteRepository = $this->entityManager->getRepository(Collecte::class);
        $handlingRepository = $this->entityManager->getRepository(Handling::class);
        $transferRequestRepository = $this->entityManager->getRepository(TransferRequest::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);

        $requests = array_merge(
            $demandeRepository->getTreatingTimesWithType(),
            $handlingRepository->getTreatingTimesWithType(),
            $collecteRepository->getTreatingTimesWithType()
        );

        $transferRequestTimes = $transferRequestRepository->getProcessingTime();

        $typeMeters = [];
        foreach ($requests as $request) {

            $validationDate = $request['validationDate'] instanceof \DateTimeInterface
                ? $request['validationDate']
                : DateTime::createFromFormat('Y-m-d H:i:s', $request['validationDate']);

            $treatingDate = $request['treatingDate'] instanceof \DateTimeInterface
                ? $request['treatingDate']
                : DateTime::createFromFormat('Y-m-d H:i:s', $request['treatingDate']);

            $typeId = $request['typeId'];

            if (!isset($typeMeters[$typeId])) {
                $typeMeters[$typeId] = [
                    'total' => 0,
                    'count' => 0
                ];
            }

            $intervalDiff = $treatingDate->diff($validationDate);
            $typeMeters[$typeId]['total'] += $this->dateService->dateIntervalToSeconds($intervalDiff);
            $typeMeters[$typeId]['count']++;
        }

        foreach($transferRequestTimes as $trTime) {
            $typeMeters[$trTime["type"]] = [
                "total" => $trTime["total"],
                "count" => $trTime["count"],
            ];
        }

        $typeIdToTypeEntity = [];

        foreach ($typeMeters as $typeId => $total) {
            $average = (int)floor($total['total'] / $total['count']);

            $type = $typeIdToTypeEntity[$typeId] ?? $typeRepository->find($typeId);

            $averageTime = $type->getAverageRequestTime();

            if (!$averageTime) {
                $averageTime = new AverageRequestTime();
                $averageTime->setType($type);
                $this->entityManager->persist($averageTime);
            }

            $averageTime->setAverage($average);
        }

        $this->entityManager->flush();
    }
}
