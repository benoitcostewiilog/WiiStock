<?php

namespace App\Service\IOT;

use App\Entity\IOT\Device;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class DeviceService
{

    private $em;
    private $IOTService;
    private $templating;

    /**
     * MessageService constructor.
     * @param EntityManagerInterface $em
     * @param IOTService $IOTService
     * @param Twig_Environment $templating
     */
    public function __construct(EntityManagerInterface $em, IOTService $IOTService, Twig_Environment $templating)
    {
        $this->em = $em;
        $this->IOTService = $IOTService;
        $this->templating = $templating;
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
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowDevice(Device $device): array {
        return [
            'code' => $device->getCode(),
            'battery' => $device->getFormattedBatteryLevel(),
            'profile' => $device->getProfile() ? $device->getProfile()->getLabel() : 'Non défini',
            'action' => $this->templating->render('IOT/device/device_action.html.twig', [
                'device' => $device->getId()
            ])
        ];
    }
}
