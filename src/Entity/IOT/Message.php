<?php


namespace App\Entity\IOT;

use App\Entity\IOT\Device as Device;
use App\Service\IOTService;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\IOT as IOTRepository;

/**
 * @ORM\Entity(repositoryClass=IOTRepository\MessageRepository::class)
 * @ORM\Table(name="iot_message")
 */
class Message
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="json")
     */
    private $config = [];

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $eventType;

    /**
     * @ORM\Column(type="string")
     */
    private $mainData;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity=Device::class, inversedBy="messages")
     * @ORM\JoinColumn(nullable=false)
     */
    private $device;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return Message
     */
    public function setConfig(array $config): Message
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDevice(): Device
    {
        return $this->device;
    }

    /**
     * @param mixed $device
     * @return Message
     */
    public function setDevice($device): Message
    {
        $this->device = $device;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDate(): \DateTime
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     * @return Message
     */
    public function setDate($date): Message
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getEventType()
    {
        return $this->eventType;
    }

    /**
     * @param mixed $eventType
     * @return Message
     */
    public function setEventType($eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMainData()
    {
        return $this->mainData;
    }

    public function getFormattedMainData() {
        $config = $this->getConfig();
        switch ($config['profile']) {
            case IOTService::INEO_SENS_ACS_TEMP:
                return $this->mainData . ' °C';
            case IOTService::INEO_SENS_GPS:
                $coordinates = explode(',', $this->mainData);
                $lat = $coordinates[0];
                $long = $coordinates[1];
                if ($lat === "-1" || $long === "-1") {
                    return 'Aucune acquisition GPS possible.';
                } else {
                    return 'LAT : ' . $lat . ', LONG : ' . $long;
                }
        }
        return 'Donnée principale non trouvée';
    }

    /**
     * @param mixed $mainData
     * @return Message
     */
    public function setMainData($mainData): self
    {
        $this->mainData = $mainData;
        return $this;
    }


}
