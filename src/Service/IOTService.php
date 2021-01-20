<?php


namespace App\Service;


use App\Controller\IOTController;
use App\Entity\IOT\Message;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class IOTService {

    const TEMP_EVENT = "EVENT";
    const TEMP_EVENT_TRESHOLD = 25;
    const TEMP_PRESENCE = "PRESENCE";

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
    public function treatMessage(Message $message): void {
        $config = $message->getConfig();
        switch ($config['profile']) {
            case IOTController::INEO_SENS_ACS_TEMP:
                $this->treatTemperatureMessage($message);
                break;
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
        if ($frame['jcd_msg_type'] === self::TEMP_EVENT) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Alerte de température',
                $this->templateService->render('mails/contents/mailTemperatureTreshold.html.twig', [
                    'device' => $message->getDevice(),
                    'temperatureReached' => $frame['jcd_temperature'],
                    'temperatureConfigured' => self::TEMP_EVENT_TRESHOLD,
                    'alertDate' => $message->getDate()->format('d/m/Y H:i:s'),
                    'batteryLevel' => $frame['jcd_battery_level'],
                ]),
                'test@wiilog.fr'
            );
        }
    }

}
