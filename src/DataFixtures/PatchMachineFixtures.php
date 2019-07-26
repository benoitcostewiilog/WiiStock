<?php

namespace App\DataFixtures;

use App\Repository\ChampsLibreRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class PatchMachineFixtures extends Fixture implements FixtureGroupInterface
{

    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;


    public function __construct(ChampsLibreRepository $champsLibreRepository)
    {
        $this->champsLibreRepository = $champsLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $path ="src/DataFixtures/Csv/machine.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ",")) !== false) {
            $rows[] = $data;
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes

        $listElements = $this->champsLibreRepository->getIdAndElementsWithMachine();

//        foreach($listElements as $elements){
//            $i = 0;
//            while($i < count($rows)) {
//                $bool = false;
//                $j = 0;
//                while ($j < count($elements['elements'])) {
//                    if ($rows[$i][0] == $elements['elements'][$j] && $rows[$i][1] != '?') {
//                        $elements['elements'][$j] = $rows[$i][1];
//                        $bool = true;
//                        break;
//                    }
//                    $j++;
//                }
//                if(!$bool && $rows[$i][1] != '?'){
//                    array_push($elements['elements'] , $rows[$i][1]);
//                }
//                $i++;
//            }
//        }

        foreach($listElements as $elements){
            $colEyelit = $colMachines= [];
            foreach($rows as $row){
                    $colMachines [] = $row[0];
                if($row[1] != '?'){
                    $colEyelit [] = $row[1];
                } else {
                    $colEyelit [] = $row[0];
                }
            }

            $newElements = str_replace($colMachines, $colEyelit, $elements['elements']);
            $champsLibre = $this->champsLibreRepository->find($elements['id']);
            $champsLibre->setElements($newElements);
        }

        $manager->flush();
        fclose($file);
    }

    public static function getGroups():array {
        return ['machine'];
    }
}
