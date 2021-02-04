<?php

namespace App\Tests;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use ReflectionClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as SfWebTestCase;

class WebTestCase extends SfWebTestCase {

    private KernelBrowser $client;

    public function __construct() {
        parent::__construct();

        $this->client = static::createClient();
    }

    protected function createAuthenticatedClient(string $login = "admin"): KernelBrowser {
        $client = $this->client();
        $user = static::$container->get(EntityManagerInterface::class)
            ->getRepository(Utilisateur::class)
            ->findByUsername($login);
        $users = static::$container->get(EntityManagerInterface::class)
            ->getRepository(Utilisateur::class)
            ->findAll();
        echo count($users);
        return $client->loginUser($user);
    }

    protected function createAnonymousClient(): KernelBrowser {
        return $this->client();
    }

    protected function invoke(&$object, $method, array ...$parameters) {
        try {
            $reflection = new ReflectionClass(get_class($object));
            $method = $reflection->getMethod($method);
            $method->setAccessible(true);

            return $method->invokeArgs($object, $parameters);
        } catch (Exception $e) {
            throw new RuntimeException($e);
        }
    }

    protected function client(): KernelBrowser {
        return $this->client;
    }

}
