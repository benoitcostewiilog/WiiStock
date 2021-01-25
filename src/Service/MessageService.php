<?php

namespace App\Service;

use App\Controller\IOTController;
use App\Entity\IOT\Device;
use App\Entity\IOT\Message;
use App\Entity\IOT\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Exception;


class MessageService
{

    private $em;

    /**
     * MessageService constructor.
     * @param $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param null $params
     * @return array
     */
    public function getDataForDatatable($params = null)
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
    private function extractMainDataFromConfig(array $config) {
        switch ($config['profile']) {
            case IOTService::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_temperature'];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    if (isset($frame['LATITUDE']) && isset($frame['LONGITUDE'])) {
                        return $frame['LATITUDE'] . ',' . $frame['LONGITUDE'];
                    } else {
                        return '-1,-1';
                    }
                }
                break;
        }
        return 'Donnée principale non trouvée';
    }

    private function extractEventTypeFromMessage(array $config) {
        switch ($config['profile']) {
            case IOTService::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_msg_type'];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    if (isset($frame['NEW_EVT_TYPE'])) {
                        return $frame['NEW_EVT_TYPE'];
                    } else if ($frame['NEW_BATT']) {
                        return 'BATTERY_INFO';
                    }
                }
                break;
        }
        return 'Évenement non trouvé';
    }

    private function extractBatteryLevelFromMessage(array $config) {
        switch ($config['profile']) {
            case IOTService::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_battery_level'];
                }
                break;
            case IOTService::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['NEW_BATT'] ?? -1;
                }
                break;
        }
        return 'Évenement non trouvé';
    }

    /**
     * @param array $message
     * @param EntityManagerInterface $entityManager
     * @return Message
     * @throws Exception
     */
    public function createMessageFromFrame(array $message, EntityManagerInterface $entityManager): Message {
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
                ->setBattery($this->extractBatteryLevelFromMessage($message));
            $entityManager->persist($device);
        } else if ($device->getBattery() < 0) {
            $device->setBattery($this->extractBatteryLevelFromMessage($message));
        }

        $messageDate = new \DateTime($message['timestamp'], new \DateTimeZone("UTC"));
        $messageDate->setTimezone(new \DateTimeZone('Europe/Paris'));
        $received = new Message();
        $received
            ->setConfig($message)
            ->setDate($messageDate)
            ->setMainData($this->extractMainDataFromConfig($message))
            ->setEventType($this->extractEventTypeFromMessage($message))
            ->setDevice($device);
        return $received;
    }
}
