<?php


namespace App\Entity\IOT;

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
     * @ORM\Column(type="bigint")
     */
    private $device;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

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
    public function getDevice()
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
    public function getDate()
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






}
