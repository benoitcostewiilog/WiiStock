<?php

namespace App\Tests;

use App\Entity\Role;
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

    private function createTestUser(EntityManagerInterface $manager, string $login): Utilisateur {
        $user = $manager->getRepository(Utilisateur::class)->findByUsername($login);
        $role = $manager->getRepository(Role::class)->findByLabel(Role::SUPER_ADMIN);
        $user = $user
            ->setUsername("unittest")
            ->setEmail("$login@test.example.com")
            ->setPassword("djezoijdioezjiodzej")
            ->setStatus(true)
            ->setMobileLoginKey("hellologintest")
            ->setRole($role);
        $manager->flush();
        if (!$user) {
            $role = $manager->getRepository(Role::class)->findByLabel(Role::SUPER_ADMIN);

            $user = (new Utilisateur())
                ->setUsername("unittest")
                ->setEmail("$login@test.example.com")
                ->setPassword("djezoijdioezjiodzej")
                ->setStatus(true)
                ->setMobileLoginKey("hellologintest")
            ->setRole($role);

            $manager->persist($user);
            $manager->flush();
        }

        return $user;
    }

    protected function createAuthenticatedClient(string $login = "unittest"): KernelBrowser {
        $manager = static::$container->get(EntityManagerInterface::class);
        $client = $this->client();

        return $client->loginUser($this->createTestUser($manager, $login));
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
