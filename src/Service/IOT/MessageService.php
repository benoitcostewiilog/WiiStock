<?php

namespace App\Service\IOT;

use App\Entity\IOT\Device;
use App\Entity\IOT\Message;
use App\Entity\IOT\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class MessageService
{

    private $em;
    private $IOTService;

    /**
     * MessageService constructor.
     * @param EntityManagerInterface $em
     * @param IOTService $IOTService
     */
    public function __construct(EntityManagerInterface $em, IOTService $IOTService)
    {
        $this->em = $em;
        $this->IOTService = $IOTService;
    }

    /**
     * @param null $params
     * @return array
     */
    public function getDataForDatatableFromDevice($params = null)
    {

        $queryResult = $this->em->getRepository(Message::class)->findByParams($params);

        $rows = [];
        foreach ($queryResult['data'] as $message) {
            $rows[] = $this->dataRowMessage($message);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Message $message
     * @return array
     */
    public function dataRowMessage(Message $message): array
    {
        $messageDate = $message->getDate()->format('d/m/Y H:i:s');
        $messageDevice = $message->getDevice();
        $messageMainData = $message->getFormattedMainData();
        $messageMainType = $message->getEventType();
        $profile = $messageDevice ? $messageDevice->getProfile() : null;
        $battery = $messageDevice ? $messageDevice->getFormattedBatteryLevel() : null;

        return [
            'date' => $messageDate,
            'device' => $messageDevice ? $messageDevice->getCode() : '',
            'mainData' => $messageMainData,
            'type' => $messageMainType,
            'profile' => $profile ? $profile->getLabel() : '',
            'battery' => $battery,
        ];
    }

    /**
     * @param array $message
     * @param EntityManagerInterface $entityManager
     * @return Message
     * @throws Exception
     */
    public function createAndPersistMessageFromFrame(array $message, EntityManagerInterface $entityManager): Message {
        $profileRepository = $entityManager->getRepository(Profile::class);
        $deviceRepository = $entityManager->getRepository(Device::class);

        $profileCode = $message['profile'];

        $profile = $profileRepository->findOneBy([
            'code' => $profileCode
        ]);

        if (!isset($profile)) {
            $profileLabel = IOTService::PROFILE_TO_ALERT[$profileCode];
            $profile = new Profile();
            $profile
                ->setCode($profileCode)
                ->setLabel($profileLabel);
            $entityManager->persist($profile);
        }

        $deviceCode = $message['device_id'];

        $device = $deviceRepository->findOneBy([
            'code' => $deviceCode
        ]);

        if (!isset($device)) {
            $device = new Device();
            $device
                ->setCode($deviceCode)
                ->setProfile($profile)
                ->setBattery(-1);
            $entityManager->persist($device);
        }

        $newBattery = $this->IOTService->extractBatteryLevelFromMessage($message);
        if ($newBattery > -1) {
            $device->setBattery($newBattery);
        }

        $messageDate = new \DateTime($message['timestamp'], new \DateTimeZone("UTC"));
        $messageDate->setTimezone(new \DateTimeZone('Europe/Paris'));
        $received = new Message();
        $received
            ->setConfig($message)
            ->setDate($messageDate)
            ->setMainData($this->IOTService->extractMainDataFromConfig($message))
            ->setEventType($this->IOTService->extractEventTypeFromMessage($message))
            ->setDevice($device);

        $entityManager->persist($received);
        return $received;
    }

    /**
     * @param array $frame
     * @param EntityManagerInterface $entityManager
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function onMessageReceived(array $frame, EntityManagerInterface $entityManager): void {
        if (isset(IOTService::PROFILE_TO_ALERT[$frame['profile']])) {
            $message = $this->createAndPersistMessageFromFrame($frame, $entityManager);
            $config = $message->getConfig();
            switch ($config['profile']) {
                case IOTService::INEO_SENS_ACS_TEMP:
                    $this->IOTService->treatTemperatureMessage($message);
                    break;
            }
            $entityManager->flush();
        }
    }
}
