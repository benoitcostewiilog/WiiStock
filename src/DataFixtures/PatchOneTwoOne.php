<?php

namespace App\DataFixtures;

use App\Entity\ChampsLibre;
use App\Repository\ChampsLibreRepository;
use App\Repository\TypeRepository;
use App\Repository\ValeurChampsLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\CategoryType;

class PatchOneTwoOne extends Fixture implements FixtureGroupInterface
{
	private $encoder;

	/**
	 * @var ChampsLibreRepository
	 */
	private $champLibreRepository;

	/**
	 * @var ValeurChampsLibreRepository
	 */
	private $valeurChampLibreRepository;

	/**
	 * @var TypeRepository
	 */
	private $typeRepository;


	public function __construct(TypeRepository $typeRepository, ValeurChampsLibreRepository $valeurChampLibreRepository, ChampsLibreRepository $champLibreRepository, UserPasswordEncoderInterface $encoder)
	{
		$this->encoder = $encoder;
		$this->champLibreRepository = $champLibreRepository;
		$this->valeurChampLibreRepository = $valeurChampLibreRepository;
		$this->typeRepository = $typeRepository;
	}

	public function load(ObjectManager $manager)
	{
		// patch spécifique pour modifier champs libres Machine (PDT) et Zone (type text-> type list)
		$labels = ['Machine (PDT)', 'Zone (PDT)'];

		foreach ($labels as $label) {

			$champLibre = $this->champLibreRepository->findOneBy(['label' => $label]);
			if (!$champLibre) {
				dump('champ libre ' . $label . ' non trouvé en base');
				return;
			}

			$champLibre->setTypage(ChampsLibre::TYPE_LIST);

			$valeursChampsLibresMachine = $this->valeurChampLibreRepository->findBy(['champLibre' => $champLibre->getId()]);
			$elements = [];
			foreach ($valeursChampsLibresMachine as $valeurChampLibre) {
				$valeur = $valeurChampLibre->getValeur();
				if (!empty($valeur) && !in_array($valeur, $elements)) {
					$elements[] = $valeur;
				}
			}
			sort($elements);
			$champLibre->setElements($elements);
		}

		$manager->flush();
	}

	public static function getGroups():array {
		return ['1.2.1'];
	}

}