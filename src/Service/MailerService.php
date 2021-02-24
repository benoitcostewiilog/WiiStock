<?php
/**
 * Created by PhpStorm.
 * User: c.gazaniol
 * Date: 24/04/2019
 * Time: 10:00
 */

namespace App\Service;


use App\Entity\MailerServer;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;

class MailerService
{

    /**
     * @var Twig_Environment
     */
    private $templating;
    private $entityManager;


    public function __construct(EntityManagerInterface $entityManager,
                                Twig_Environment $templating)
    {
        $this->entityManager = $entityManager;
        $this->templating = $templating;
    }

    public function sendMail($subject, $content, $to)
    {
        if (isset($_SERVER['APP_NO_MAIL']) && $_SERVER['APP_NO_MAIL'] == 1) {
    		return true;
		}
        $mailerServerRepository = $this->entityManager->getRepository(MailerServer::class);
        $mailerServer = $mailerServerRepository->findOneMailerServer();
        if ($mailerServer) {
            $user = $mailerServer->getUser() ?? '';
            $password = $mailerServer->getPassword() ?? '';
            $host = $mailerServer->getSmtp() ?? '';
            $port = $mailerServer->getPort() ?? '';
            $protocole = $mailerServer->getProtocol() ?? '';
            $senderName = $mailerServer->getSenderName() ?? '';
            $senderMail = $mailerServer->getSenderMail() ?? '';

        } else {
            return false;
        }


        if (empty($user) || empty($password) || empty($host) || empty($port)) {
            return false;
        }

        //protection dev
        if (!isset($_SERVER['APP_ENV']) || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] !== 'prod')) {
            $content .= '<p>DESTINATAIRES : ';
            if (!is_array($to)) {
                $content .= $to;
            } else {
                foreach($to as $dest) {
                    $content .= $dest . ', ';
                }
            }
            $content .= '</p>';
            $to = ['test@wiilog.fr'];
        }

        $transport = (new \Swift_SmtpTransport($host, $port, $protocole))
            ->setUsername($user)
            ->setPassword($password);

        $message = (new \Swift_Message());


		$message
            ->setFrom($senderMail, $senderName)
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($content)
            ->setContentType('text/html');

        $mailer = (new \Swift_Mailer($transport));
        $mailer->send($message);
    }
}
