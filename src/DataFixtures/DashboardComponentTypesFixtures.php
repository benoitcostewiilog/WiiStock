<?php

namespace App\DataFixtures;

use App\Entity\Dashboard;
use App\Service\SpecificService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class DashboardComponentTypesFixtures extends Fixture implements FixtureGroupInterface {

    private $encoder;
    private $specificService;
    private $output;

    private const COMPONENT_TYPES = [
        'Quantité en cours sur n emplacement(s)' => [
            'hint' => 'Nombre de colis en encours sur les emplacements sélectionnés',
            'exampleValues' => [
                'title' => 'Litige en cours',
                'count' => 5,
                'subtitle' => 'Litige',
                'delay' => 20634860
            ],
            'category' => Dashboard\ComponentType::INDICATOR_TYPE,
            'meterKey' => Dashboard\ComponentType::ONGOING_PACKS,
            'template' => Dashboard\ComponentType::ONGOING_PACKS,
        ],
        'Nombre d\'arrivages quotidiens' => [
            'hint' => 'Nombre d\'arrivages créés par jour',
            'exampleValues' => [],
            'category' => Dashboard\ComponentType::GRAPH_TYPE,
            'template' => null,
            'meterKey' => Dashboard\ComponentType::DAILY_ARRIVALS,
        ],
        'Suivi des transporteur' => [
            'hint' => 'Transporteur ayant effectué un arrivage dans la journée',
            'exampleValues' => [
                'carriers' => [
                    'TRANS1',
                    'TRANS2',
                    'TRANS3',
                    'TRANS4',
                ]
            ],
            'category' => Dashboard\ComponentType::INDICATOR_TYPE,
            'template' => 'carrier_tracking',
            'meterKey' => Dashboard\ComponentType::CARRIER_INDICATOR,
        ],
        'Colis en retard' => [
            'hint' => 'Les 100 colis les plus anciens ayant dépassé le délai de présence sur leur emplacement',
            'exampleValues' => null,
            'category' => Dashboard\ComponentType::INDICATOR_TYPE,
            'template' => null,
            'meterKey' => Dashboard\ComponentType::LATE_PACKS,
        ],
        'Nombre d\'arrivages et de colis quotidiens' => [
            'hint' => 'Nombre d\'arrivages et de colis créés par jour',
            'exampleValues' => null,
            'category' => Dashboard\ComponentType::GRAPH_TYPE,
            'template' => Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS,
            'meterKey' => Dashboard\ComponentType::DAILY_ARRIVALS_AND_PACKS,
        ],
        'Nombre d\'arrivages et de colis hebdomadaires' => [
            'hint' => 'Nombre d\'arrivage et de colis créés par semaine',
            'exampleValues' => null,
            'category' => Dashboard\ComponentType::GRAPH_TYPE,
            'template' => null,
            'meterKey' => Dashboard\ComponentType::WEEKLY_ARRIVALS_AND_PACKS,
        ],
    ];

    public function __construct(UserPasswordEncoderInterface $encoder,
                                SpecificService $specificService) {
        $this->encoder = $encoder;
        $this->specificService = $specificService;
        $this->output = new ConsoleOutput();
    }

    public function load(ObjectManager $manager) {
        $componentTypeRepository = $manager->getRepository(Dashboard\ComponentType::class);
        $alreadyExisting = $componentTypeRepository->findAll();
        $alreadyExistingName = [];

        // remove unused ComponentType
        foreach($alreadyExisting as $componentType) {
            $name = $componentType->getName();
            if(!isset(self::COMPONENT_TYPES[$name])) {
                $manager->remove($componentType);
                $this->output->writeln("Component Type \"$name\" removed");
            } else {
                $alreadyExistingName[$name] = $componentType;
            }
        }

        // we persist new ComponentType
        foreach(self::COMPONENT_TYPES as $name => $config) {
            $componentType = $alreadyExistingName[$name] ?? null;
            $componentTypeExisted = isset($componentType);
            if(!$componentTypeExisted) {
                $componentType = new Dashboard\ComponentType();
                $componentType->setName($name);
                $manager->persist($componentType);
            }

            $componentType
                ->setHint($config['hint'] ?? null)
                ->setExampleValues($config['exampleValues'] ?? [])
                ->setCategory($config['category'] ?? null)
                ->setMeterKey($config['meterKey'] ?? null)
                ->setTemplate($config['template'] ?? null);

            $action = !$componentTypeExisted ? 'persisted' : 'updated';
            $this->output->writeln("Component Type \"$name\" $action");
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }

}
