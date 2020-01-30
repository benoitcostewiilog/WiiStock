<?php

namespace App\DataFixtures;

use App\Entity\ParametrageGlobal;
use App\Entity\Parametre;

use App\Repository\ParametrageGlobalRepository;
use App\Repository\ParametreRepository;

use App\Service\SpecificService;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Common\Persistence\ObjectManager;

class ParametreFixtures extends Fixture implements FixtureGroupInterface
{

	/**
	 * @var ParametreRepository
	 */
	private $parametreRepository;

	/**
	 * @var ParametrageGlobalRepository
	 */
	private $parametreGlobalRepository;

	/**
	 * @var SpecificService
	 */
	private $specificService;

    public function __construct(
    	ParametreRepository $parametreRepository,
		ParametrageGlobalRepository $parametrageGlobalRepository,
		SpecificService $specificService)
    {
    	$this->parametreRepository = $parametreRepository;
    	$this->parametreGlobalRepository = $parametrageGlobalRepository;
    	$this->specificService = $specificService;
    }

    public function load(ObjectManager $manager)
    {
		$parameters = [
			[
				'label' => Parametre::LABEL_AJOUT_QUANTITE,
				'type' => Parametre::TYPE_LIST,
				'elements' => [Parametre::VALUE_PAR_ART, Parametre::VALUE_PAR_REF],
				'default' => Parametre::VALUE_PAR_REF
			]
		];

		foreach ($parameters as $parameter) {
			$param = $this->parametreRepository->findBy(['label' => $parameter['label']]);

			if (empty($param)) {
				$param = new Parametre();
				$param
					->setLabel($parameter['label'])
					->setTypage($parameter['type'])
					->setDefaultValue($parameter['default'])
					->setElements($parameter['elements']);
				$manager->persist($param);
				dump("création du paramètre " . $parameter['label']);
			}
		}

		$globalParameterLabels = [
			ParametrageGlobal::CREATE_DL_AFTER_RECEPTION => [
			    'default' => false,
                SpecificService::CLIENT_COLLINS => true
            ],
			ParametrageGlobal::CREATE_PREPA_AFTER_DL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS => true
            ],
			ParametrageGlobal::INCLUDE_BL_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS => true
            ],
			ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL => [
                'default' => true,
                SpecificService::CLIENT_SAFRAN_ED => false
            ],
            ParametrageGlobal::USES_UTF8 => [
                'default' => true,
            ],
            ParametrageGlobal::BARCODE_TYPE_IS_128 => [
                'default' => true,
            ],
		];

		foreach ($globalParameterLabels as $globalParameterLabel => $values) {
			$globalParam = $this->parametreGlobalRepository->findBy(['label' => $globalParameterLabel]);

			if (empty($globalParam)) {
                $appClient = $this->specificService->getAppClient();
                $value = isset($values[$appClient])
                    ? $values[$appClient]
                    : $values['default'];

				$globalParam = new ParametrageGlobal();
				$globalParam
					->setLabel($globalParameterLabel)
					->setParametre($value);
				$manager->persist($globalParam);
				dump("création du paramètre " . $globalParameterLabel);
			}
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['param', 'fixtures'];
    }
}
