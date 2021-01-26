<?php


namespace App\Service\IOT;

use App\Entity\IOT\Message;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class IOTService {

    const TEMP_EVENT = "EVENT";
    const TEMP_EVENT_TRESHOLD = 25;
    const INEO_SENS_ACS_TEMP = 'ineo-sens-acs';
    const INEO_SENS_GPS = 'trk-tracer-gps-new';

    const PROFILE_TO_ALERT = [
        self::INEO_SENS_ACS_TEMP => 'Capteur de température Ineo-Sens',
        self::INEO_SENS_GPS => 'Capteur GPS Ineo-Sens'
    ];

    private $mailerService;
    private $templateService;

    /**
     * IOTService constructor.
     * @param MailerService $mailerService
     * @param Twig_Environment $templateService
     */
    public function __construct(MailerService $mailerService, Twig_Environment $templateService)
    {
        $this->mailerService = $mailerService;
        $this->templateService = $templateService;
    }

    /**
     * @param Message $message
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function treatTemperatureMessage(Message $message) {
        $frame = $message->getConfig()['payload'][0]['data'];
        $device = $message->getDevice();
        if ($frame['jcd_msg_type'] === self::TEMP_EVENT) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Alerte de température',
                $this->templateService->render('mails/contents/mailTemperatureTreshold.html.twig', [
                    'device' => $device ? $device->getCode() : '',
                    'temperatureReached' => $message->getFormattedMainData(),
                    'temperatureConfigured' => self::TEMP_EVENT_TRESHOLD,
                    'alertDate' => $message->getDate()->format('d/m/Y H:i:s'),
                    'batteryLevel' => $device ? $device->getFormattedBatteryLevel() : '',
                ]),
                'test@wiilog.fr'
            );
        }
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
