<?php


namespace App\Service;


use App\Entity\IOT\Message;

class IOTService {

    public function treatMessage(Message $message): array {
        $config = $message->getConfig();

    }

}
