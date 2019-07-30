<?php

namespace App\DataFixtures;

use App\Entity\ParamClient;
use App\Entity\Role;
use App\Repository\ParamClientRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;


class safranFixture extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ParamClientRepository
     */
    private $paramClientRepository;

    public function __construct(ParamClientRepository $paramClientRepository)
    {
        $this->paramClientRepository = $paramClientRepository;
    }

    public function load(ObjectManager $manager)
    {
		$rolesLabels = [
			Role::DEM_SAFRAN
		];

		foreach ($rolesLabels as $roleLabel) {
			$role = $this->roleRepository->findByLabel($roleLabel);

			if (empty($role)) {
				$role = new Role();
				$role
					->setLabel($roleLabel)
					->setActive(true);

				$manager->persist($role);
				dump("création du rôle " . $roleLabel);
			}
		}

		$manager->flush();
    }

    public static function getGroups():array {
        return ['safran'];
    }

}
