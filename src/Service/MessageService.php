<?php

namespace App\Service;

use App\Controller\IOTController;
use App\Entity\IOT\Message;
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
        $messageProfile = IOTService::PROFILE_TO_ALERT[$message->getProfileCode()];

        return [
            'date' => $messageDate,
            'device' => $messageDevice,
            'mainData' => $messageMainData,
            'type' => $messageMainType,
            'profile' => $messageProfile,
            'battery' => $message->getBatteryLevel() < 0 ? 'Non remontée sur la trame' : $message->getBatteryLevel(),
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
                    return $frame['NEW_EVT_TYPE'];
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
     * @return Message
     * @throws Exception
     */
    public function createMessageFromFrame(array $message): Message {
        $message['profileLabel'] = IOTService::PROFILE_TO_ALERT[$message['profile']];
        $messageDate = new \DateTime($message['timestamp'], new \DateTimeZone("UTC"));
        $messageDate->setTimezone(new \DateTimeZone('Europe/Paris'));
        $received = new Message();
        $received
            ->setConfig($message)
            ->setDate($messageDate)
            ->setProfileCode($message['profile'])
            ->setMainData($this->extractMainDataFromConfig($message))
            ->setEventType($this->extractEventTypeFromMessage($message))
            ->setBatteryLevel($this->extractBatteryLevelFromMessage($message))
            ->setDevice($message['device_id'] ?? -1);
        return $received;
    }
}
