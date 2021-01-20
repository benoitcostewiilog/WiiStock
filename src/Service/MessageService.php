<?php

namespace App\Service;

use App\Controller\IOTController;
use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Emplacement;
use App\Entity\FiltreSup;
use App\Entity\Fournisseur;
use App\Entity\Import;
use App\Entity\InventoryCategory;
use App\Entity\IOT\Message;
use App\Entity\MouvementStock;
use App\Entity\ParametrageGlobal;
use App\Entity\ReceptionReferenceArticle;
use App\Entity\Attachment;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Exceptions\ImportException;
use App\Helper\Stream;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


class MessageService
{

    private $em;

    /**
     * MessageService constructor.
     * @param $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }


    /**
     * @param null $params
     * @return array
     */
    public function getDataForDatatable($params = null)
    {

        $queryResult = $this->em->getRepository(Message::class)->findByParams($params);

        $rows = [];
        foreach ($queryResult['data'] as $message) {
            $rows[] = $this->dataRowMessage($message);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Message $message
     * @return array
     */
    public function dataRowMessage(Message $message): array
    {
        $messageDate = $message->getDate()->format('d/m/Y H:i:s');
        $messageDevice = $message->getDevice();
        $messageMainData = $this->getMainDataFromMessage($message);
        $messageMainType = $this->getEventTypeFromMessage($message);

        return [
            'date' => $messageDate,
            'device' => $messageDevice,
            'mainData' => $messageMainData,
            'type' => $messageMainType,
        ];
    }

    private function getMainDataFromMessage(Message $message) {
        $config = $message->getConfig();
        switch ($config['profile']) {
            case IOTController::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_temperature'] . 'C°';
                }
        }
        return 'CONFIGURATION';
    }

    private function getEventTypeFromMessage(Message $message) {
        $config = $message->getConfig();
        switch ($config['profile']) {
            case IOTController::INEO_SENS_ACS_TEMP:
                if (isset($config['payload'])) {
                    $frame = $config['payload'][0]['data'];
                    return $frame['jcd_msg_type'];
                }
        }
        return 'CONFIGURATION';
    }
}
