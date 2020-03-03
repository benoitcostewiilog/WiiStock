<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Colis;
use App\Entity\Emplacement;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

Class ColisService
{

    private $entityManager;
    private $mouvementTracaService;
    private $specificService;

    public function __construct(MouvementTracaService $mouvementTracaService,
                                SpecificService $specificService,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->specificService = $specificService;
        $this->mouvementTracaService = $mouvementTracaService;
    }

    public function persistColis(Arrivage $arrivage, Nature $nature): Colis {
        $arrivageNum = $arrivage->getNumeroArrivage();

        $highestCounter = $this->getHighestCodeByPrefix($arrivage) + 1;
        $newCounter = sprintf('%05u', $highestCounter);

        $colis = new Colis();
        $code = ($nature->getPrefix() ?? '') . $arrivageNum . '-' . $newCounter;
        $colis
            ->setCode($code)
            ->setNature($nature);

        $arrivage->addColis($colis);

        $this->entityManager->persist($colis);
        return $colis;
    }

    public function getHighestCodeByPrefix(Arrivage $arrivage): int {
        /** @var Colis $lastColis */
        $lastColis = $arrivage->getColis()->last();
        $lastCode = $lastColis ? $lastColis->getCode() : null;
        $lastCodeSplitted = isset($lastCode) ? explode('-', $lastCode) : null;
        return (int) ((isset($lastCodeSplitted) && count($lastCodeSplitted) > 1)
            ? $lastCodeSplitted[1]
            : 0);
    }

    /**
     * @param Arrivage $arrivage
     * @param array $colisByNatures
     * @return Colis[]
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function persistMultiColis(Arrivage $arrivage,
                                      array $colisByNatures,
                                      $user): array {
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $natureRepository = $this->entityManager->getRepository(Nature::class);
        $defaultEmpForMvt = ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED) && $arrivage->getDestinataire())
            ? $emplacementRepository->findOneByLabel(SpecificService::ECS_ARG_LOCATION)
            : null;
        if (!isset($defaultEmpForMvt)) {
            $defaultEmpForMvtParam = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MVT_DEPOSE_DESTINATION);
            $defaultEmpForMvt = !empty($defaultEmpForMvtParam)
                ? $emplacementRepository->find($defaultEmpForMvtParam)
                : null;
        }
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $colisList = [];
        foreach ($colisByNatures as $natureId => $number) {
            $nature = $natureRepository->find($natureId);
            for ($i = 0; $i < $number; $i++) {
                $colis = $this->persistColis($arrivage, $nature);
                if ($defaultEmpForMvt) {
                    $mouvementDepose = $this->mouvementTracaService->persistMouvementTraca(
                        $colis->getCode(),
                        $defaultEmpForMvt,
                        $user,
                        $now,
                        false,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        ['from' => $arrivage]
                    );
                    $this->entityManager->persist($mouvementDepose);
                }
                $colisList[] = $colis;
            }
        }

        return $colisList;
    }
}
