<?php


namespace App\Service;


use App\Controller\IOTController;
use App\Entity\IOT\Message;
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
    private $messageService;

    /**
     * IOTService constructor.
     * @param MailerService $mailerService
     * @param Twig_Environment $templateService
     * @param MessageService $messageService
     */
    public function __construct(MailerService $mailerService, Twig_Environment $templateService, MessageService $messageService)
    {
        $this->mailerService = $mailerService;
        $this->templateService = $templateService;
        $this->messageService = $messageService;
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
        if (isset(self::PROFILE_TO_ALERT[$message['profile']])) {
            $message = $this->messageService->createMessageFromFrame($frame, $entityManager);
            $entityManager->persist($message);
            $config = $message->getConfig();
            switch ($config['profile']) {
                case self::INEO_SENS_ACS_TEMP:
                    $this->treatTemperatureMessage($message);
                    break;
            }
            $entityManager->flush();
        }
    }

    /**
     * @param Message $message
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function treatTemperatureMessage(Message $message) {
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

}
