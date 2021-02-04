<?php

namespace App\DataFixtures\Tests;

use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class PopulateFixture extends Fixture implements FixtureGroupInterface {

    /** @Required  */
    public UserService $userService;

    /** @Required  */
    public EntityManagerInterface $entityManager;

    /** @Required  */
    public UserPasswordEncoderInterface $userPasswordEncoder;

    public static function getGroups(): array {
        return ["test"];
    }

    public function load(ObjectManager $manager) {
        $roleRepository = $manager->getRepository(Role::class);
        $userRepository = $manager->getRepository(Utilisateur::class);

        $user = $userRepository->findByUsername("admin");
        if ($user === null) {
            $user = new Utilisateur();
        }

        $user->setUsername("admin")
            ->setEmail("admin@wiilog.fr")
            ->setRole($roleRepository->findByLabel(Role::SUPER_ADMIN))
            ->setStatus(true)
            ->setPassword($this->userPasswordEncoder->encodePassword($user, "testadmin"))
            ->setMobileLoginKey("6s74f89ze4s56d");

        $manager->persist($user);
        $manager->flush();
    }

}
