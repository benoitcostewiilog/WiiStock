<?php

namespace App\Service\IOT;

use App\Entity\IOT\Device;
use Doctrine\ORM\EntityManagerInterface;


class DeviceService
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
    public function getDataForDatatable($params = null)
    {

        $queryResult = $this->em->getRepository(Device::class)->findByParams($params);

        $rows = [];
        foreach ($queryResult['data'] as $message) {
            $rows[] = $this->dataRowDevice($message);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Device $device
     * @return array
     */
    public function dataRowDevice(Device $device): array {
        return [
            'code' => $device->getCode(),
            'battery' => $device->getFormattedBatteryLevel(),
            'profile' => $device->getProfile() ? $device->getProfile()->getLabel() : 'Non défini',
        ];
    }
}
