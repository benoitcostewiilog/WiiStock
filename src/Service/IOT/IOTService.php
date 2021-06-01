<?php


namespace App\Service\IOT;


use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Entity\IOT\SensorProfile;
use Doctrine\ORM\EntityManagerInterface;

class IOTService
{
    const INEO_SENS_ACS_TEMP = 'ineo-sens-acs';
    const INEO_SENS_ACS_BTN = 'acs-switch-bouton';
    const INEO_SENS_GPS = 'trk-tracer-gps-new';

    const PROFILE_TO_MAX_TRIGGERS = [
        self::INEO_SENS_ACS_TEMP => 1,
        self::INEO_SENS_GPS => 1,
        self::INEO_SENS_ACS_BTN => 1,
    ];

    const PROFILE_TO_TYPE = [
        self::INEO_SENS_ACS_TEMP => 'Capteur de température',
        self::INEO_SENS_GPS => 'Capteur GPS',
        self::INEO_SENS_ACS_BTN => 'Action',
    ];

    const PROFILE_TO_FREQUENCY = [
        self::INEO_SENS_ACS_TEMP => 'x minutes',
        self::INEO_SENS_GPS => 'x minutes',
        self::INEO_SENS_ACS_BTN => 'à l\'action',
    ];

    public function onMessageReceived(array $frame, EntityManagerInterface $entityManager) {
        if (isset(self::PROFILE_TO_TYPE[$frame['profile']])) {
            $message = $this->parseMessage($frame, $entityManager);
            $config = $message->getPayload();
            switch ($config['profile']) {
                case IOTService::INEO_SENS_ACS_TEMP:
                    // treat temperature message receival
                    break;
            }
            $entityManager->flush();
        }
    }

    private function parseMessage(array $message, EntityManagerInterface $entityManager): SensorMessage
    {
        $profileRepository = $entityManager->getRepository(SensorProfile::class);
        $deviceRepository = $entityManager->getRepository(Sensor::class);

        $profileCode = $message['profile'];

        $profile = $profileRepository->findOneBy([
            'name' => $profileCode
        ]);

        if (!isset($profile)) {
            $profile = new SensorProfile();
            $profile
                ->setName($profileCode)
                ->setMaxTriggers(self::PROFILE_TO_MAX_TRIGGERS[$profileCode] ?? 1);
            $entityManager->persist($profile);
        }
        $entityManager->flush();
        $deviceCode = $message['device_id'];

        $device = $deviceRepository->findOneBy([
            'code' => $deviceCode
        ]);

        if (!isset($device)) {
            $device = new Sensor();
            $device
                ->setCode($deviceCode)
                ->setProfile($profile)
                ->setBattery(-1)
                ->setFrequency(self::PROFILE_TO_FREQUENCY[$profileCode] ?? 'jamais')
                ->setType(self::PROFILE_TO_TYPE[$profileCode] ?? 'Type non détecté');
            $entityManager->persist($device);
        }

        $newBattery = $this->extractBatteryLevelFromMessage($message);
        if ($newBattery > -1) {
            $device->setBattery($newBattery);
        }
        $entityManager->flush();

        $messageDate = new \DateTime($message['timestamp'], new \DateTimeZone("UTC"));
        $messageDate->setTimezone(new \DateTimeZone('Europe/Paris'));
        $received = new SensorMessage();
        $received
            ->setPayload($message)
            ->setDate($messageDate)
            ->setContent($this->extractMainDataFromConfig($message))
            ->setEvent($this->extractEventTypeFromMessage($message))
            ->setSensor($device);

        $entityManager->persist($received);
        return $received;
    }

    public function extractMainDataFromConfig(array $config) {
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

    public function extractEventTypeFromMessage(array $config) {
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

    public function extractBatteryLevelFromMessage(array $config) {
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
}
