<?php


namespace App\Service;


use App\Controller\IOTController;
use App\Entity\IOT\Message;
use Doctrine\ORM\EntityManagerInterface;
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
     * @param EntityManagerInterface $entityManager
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function treatMessage(Message $message, EntityManagerInterface $entityManager): void {
        $config = $message->getConfig();
        switch ($config['profile']) {
            case IOTController::INEO_SENS_ACS_TEMP:
                $this->treatTemperatureMessage($message, $entityManager);
                break;
        }
    }

    /**
     * @param Message $message
     * @param EntityManagerInterface $entityManager
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \Exception
     */
    private function treatTemperatureMessage(Message $message, EntityManagerInterface $entityManager) {
        $payload = $message->getConfig()['payload'][0];
        $frame = $payload['data'];
        $timeStamp = new \DateTime($payload['timestamp'], new \DateTimeZone(\DateTimeZone::UTC));
        $timeStamp->setTimezone(new \DateTimeZone('Europe/Paris'));
        if ($frame['jcd_msg_type'] === self::TEMP_EVENT) {
            $this->mailerService->sendMail(
                'FOLLOW GT // Alerte de température atteint',
                $this->templateService->render('mails/contents/mailTemperatureTreshold.html.twig', [
                    'device' => $message->getDevice(),
                    'temperatureReached' => $frame['jcd_temperature'],
                    'temperatureConfigured' => self::TEMP_EVENT_TRESHOLD,
                    'alertDate' => $timeStamp->format('d/m/Y H:i:s'),
                    'batteryLevel' => $frame['jcd_battery_level'],
                ]),
                $entityManager
            );
        }
    }

}
