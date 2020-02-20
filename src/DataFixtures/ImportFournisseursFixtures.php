<?php

namespace App\DataFixtures;

use App\Entity\Fournisseur;
use App\Repository\FournisseurRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class ImportFournisseursFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    public function __construct(FournisseurRepository $fournisseurRepository)
    {
        $this->fournisseurRepository = $fournisseurRepository;
    }

    public function load(ObjectManager $manager)
    {
		$path = "src/DataFixtures/fournisseurs.csv";
		$file = fopen($path, "r");

        $firstRow = true;

        while (($data = fgetcsv($file, 1000, ";")) !== false) {
        	if ($firstRow) {
        		$firstRow = false;
			} else {
				$row = array_map('utf8_encode', $data);
				$code = $row[1];
				$fournisseur = $this->fournisseurRepository->findOneByCodeReference($code);

				if (empty($fournisseur)) {
					$fournisseur = new Fournisseur();
					$fournisseur
						->setNom($row[0])
						->setCodeReference($code);
					$manager->persist($fournisseur);
        			$manager->flush();
				}
			}
        }
    }

	public static function getGroups(): array
	{
		return ['import-fournisseurs'];
	}
}
