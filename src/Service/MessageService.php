<?php

namespace App\Service;

use App\Controller\IOTController;
use App\Entity\IOT\Message;
use Doctrine\ORM\EntityManagerInterface;


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
        $messageMainData = $this->getMainDataFromMessage($message);
        $messageMainType = $this->getEventTypeFromMessage($message);
        $messageProfile = $message->getConfig()['profileLabel'];

        return [
            'date' => $messageDate,
            'device' => $messageDevice,
            'mainData' => $messageMainData,
            'type' => $messageMainType,
            'profile' => $messageProfile,
        ];
    }

    private function getMainDataFromMessage(Message $message) {
        $config = $message->getConfig();
        switch ($config['profile']) {
            case IOTController::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_temperature'] . ' °C';
                }
                break;
            case IOTController::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    if (isset($frame['LATITUDE']) && isset($frame['LONGITUDE'])) {
                        return 'LAT : ' . $frame['LATITUDE'] . ', LONG : ' . $frame['LONGITUDE'];
                    } else {
                        return 'Aucune coordonnée GPS acquise';
                    }
                }
                break;
        }
        return 'CONFIGURATION';
    }

    private function getEventTypeFromMessage(Message $message) {
        $config = $message->getConfig();
        switch ($config['profile']) {
            case IOTController::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_msg_type'];
                }
                break;
            case IOTController::INEO_SENS_GPS:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['NEW_EVT_TYPE'];
                }
                break;
        }
        return 'CONFIGURATION';
    }
}
