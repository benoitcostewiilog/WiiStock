<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\Livraison;
use App\Entity\NotificationTemplate;
use App\Entity\OrdreCollecte;
use App\Entity\Preparation;
use App\Entity\TransferOrder;
use Doctrine\ORM\EntityManagerInterface;
use Google_Client;
use Symfony\Component\HttpKernel\KernelInterface;

class NotificationService
{

    public const CHANNELS = [
        Livraison::class => "stock",
        Preparation::class => "stock",
        OrdreCollecte::class => "stock",
        TransferOrder::class => "stock",
        Dispatch::class => "tracking",
        Handling::class => "demande",
    ];

    public const TYPE_BY_CLASS = [
        Preparation::class => NotificationTemplate::PREPARATION,
        Livraison::class => NotificationTemplate::DELIVERY,
        OrdreCollecte::class => NotificationTemplate::COLLECT,
        TransferOrder::class => NotificationTemplate::TRANSFER,
        Dispatch::class => NotificationTemplate::DISPATCH,
        Handling::class => NotificationTemplate::HANDLING,
    ];

    public const READABLE_TYPES = [
        NotificationTemplate::PREPARATION => "Ordre de préparation",
        NotificationTemplate::DELIVERY => "Ordre de livraison",
        NotificationTemplate::COLLECT => "Ordre de collecte",
        NotificationTemplate::TRANSFER => "Ordre de transfert",
        NotificationTemplate::DISPATCH => "Demande d'acheminement",
        NotificationTemplate::HANDLING => "Demande de service",
    ];

    public const DICTIONARIES = [
        NotificationTemplate::DELIVERY => VariableService::DELIVERY_DICTIONARY,
        NotificationTemplate::PREPARATION => VariableService::PREPARATION_DICTIONARY,
        NotificationTemplate::COLLECT => VariableService::COLLECT_DICTIONARY,
        NotificationTemplate::TRANSFER => VariableService::TRANSFER_DICTIONARY,
        NotificationTemplate::DISPATCH => VariableService::DISPATCH_DICTIONARY,
        NotificationTemplate::HANDLING => VariableService::HANDLING_DICTIONARY,
    ];

    private const FCM_PLUGIN_ACTIVITY = 'FCM_PLUGIN_ACTIVITY';

    /** @Required */
    public EntityManagerInterface $manager;

    /** @Required */
    public VariableService $variableService;

    /** @Required */
    public KernelInterface $kernel;

    public function toTreat($entity)
    {
        $type = NotificationService::GetTypeFromEntity($entity);
        $channel = NotificationService::GetChannelFromEntity($entity);
        $title = NotificationService::READABLE_TYPES[$type] ?? '';
        $emergency = $this->compareEmergencies($entity);
        $imageURI = null;
        if ($emergency) {
            $title .= ' [URGENT] ' . $entity->getEmergency();
            $imageURI = $_SERVER['APP_URL'] . '/img/notification_alert.png';
        }

        $notificationTemplateRepository = $this->manager->getRepository(NotificationTemplate::class);

        $template = $notificationTemplateRepository->findByType($type);

        $this->send(
            $channel,
            $title,
            $this->variableService->replaceVariables($template->getContent(), $entity),
            [
                "type" => $type,
                "id" => strval($entity->getId()),
                'image' => $imageURI
            ],
            $imageURI
        );
    }

    public function send(string $channel,
                         string $title,
                         string $content,
                         ?array $data = null,
                         ?string $imageURI = null) {
        $client = $this->configureClient();
        $httpClient = $client->authorize();
        $response = $httpClient->request(
            'POST',
            'https://fcm.googleapis.com/v1/projects/follow-gt/messages:send', [
            'json' => [
                'message' => [
                    'topic' => $_SERVER["APP_INSTANCE"] . "-" . $channel,
                    'notification' => [
                        'title' => $title,
                        'body' => $content,
                        'image' => $imageURI
                    ],
                    'data' => $data ?? [],
                    'android' => [
                        "notification" => [
                            "click_action" => self::FCM_PLUGIN_ACTIVITY
                        ]
                    ]
                ], 'validate_only' => false
            ]
        ]);
    }

    public static function GetTypeFromEntity($entity): ?string {
        return self::GetValueFromEntityKey(self::TYPE_BY_CLASS, $entity);
    }

    private function compareEmergencies($entity): bool {
        if ($entity instanceof Handling or $entity instanceof Dispatch) {
            return $entity->getType()->isNotificationsEmergency($entity->getEmergency());
        } else {
            return false;
        }
    }

    public static function GetChannelFromEntity($entity): ?string {
        $res = null;
        if ($entity instanceof Preparation) {
            $res = "stock-delivery-" . $entity->getDemande()->getType()->getId();
        } else if ($entity instanceof Livraison) {
            $res = "stock-delivery-" . $entity->getPreparation()->getDemande()->getType()->getId();
        } else if ($entity instanceof Dispatch) {
            $res = "tracking-dispatch-" . $entity->getType()->getId();
        } else if ($entity instanceof Handling) {
            $res = "demande-handling-" . $entity->getType()->getId();
        } else if ($entity instanceof OrdreCollecte) {
            $res = "stock";
        } else if ($entity instanceof TransferOrder) {
            $res = "tracking";
        }
        return $res;
    }

    private static function GetValueFromEntityKey(array $array, $entity) {
        $res = null;
        foreach($array as $class => $value) {
            if (is_a($entity, $class)) {
                $res = $value;
                break;
            }
        }
        return $res;
    }

    private function configureClient(): Google_Client {
        $client = new Google_Client();
        $client->setAuthConfig($this->kernel->getProjectDir() . '/config/fcm-config.json');
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $client->setAccessToken($accessToken);
        return $client;
    }
}
