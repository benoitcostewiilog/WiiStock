<?php


namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\FiltreSup;
use App\Entity\Pack;
use App\Entity\Emplacement;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\Utilisateur;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Environment as Twig_Environment;


Class PackService
{

    private $entityManager;
    private $security;
    private $template;
    private $mouvementTracaService;
    private $specificService;

    public function __construct(MouvementTracaService $mouvementTracaService,
                                SpecificService $specificService,
                                Security $security,
                                Twig_Environment $template,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->specificService = $specificService;
        $this->mouvementTracaService = $mouvementTracaService;
        $this->security = $security;
        $this->template = $template;
    }

    /**
     * @param array|null $params
     * @return array
     * @throws Exception
     */
    public function getDataForDatatable($params = null)
    {
        $filtreSupRepository = $this->entityManager->getRepository(FiltreSup::class);
        $packRepository = $this->entityManager->getRepository(Pack::class);

        $filters = $filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_PACK, $this->security->getUser());
        $queryResult = $packRepository->findByParamsAndFilters($params, $filters);

        $packs = $queryResult['data'];

        $rows = [];
        foreach ($packs as $pack) {
            $rows[] = $this->dataRowPack($pack);
        }

        return [
            'data' => $rows,
            'recordsFiltered' => $queryResult['count'],
            'recordsTotal' => $queryResult['total'],
        ];
    }

    /**
     * @param Pack $pack
     * @return array
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function dataRowPack(Pack $pack)
    {
        if ($pack->getArrivage()) {
            $fromPath = 'arrivage_show';
            $fromLabel = 'arrivage.arrivage';
            $fromEntityId = $pack->getArrivage()->getId();
            $originFrom = $pack->getArrivage()->getNumeroArrivage();
        } else {
            $fromPath = null;
            $fromEntityId = null;
            $fromLabel = null;
            $originFrom = '-';
        }

        /** @var MouvementTraca $lastPackMovement */
        $lastPackMovement = $pack->getLastTracking();
        return [
            'packNum' => $pack->getCode(),
            'packNature' => $pack->getNature() ? $pack->getNature()->getLabel() : '',
            'packLastDate' => $lastPackMovement
                ? ($lastPackMovement->getDatetime()
                    ? $lastPackMovement->getDatetime()->format('d/m/Y \à H:i:s')
                    : '')
                : '',
            'packOrigin' => $this->template->render('mouvement_traca/datatableMvtTracaRowFrom.html.twig', [
                'from' => $originFrom,
                'fromLabel' => $fromLabel,
                'entityPath' => $fromPath,
                'entityId' => $fromEntityId
            ]),
            'packLocation' => $lastPackMovement
                ? ($lastPackMovement->getEmplacement()
                    ? $lastPackMovement->getEmplacement()->getLabel()
                    : '')
                : ''
        ];
    }

    /**
     * @param array $options Either ['arrival' => Arrivage, 'nature' => Nature] or ['code' => string]
     * @return Pack
     */
    public function createPack(array $options = []): Pack {
        if (!empty($options['code'])) {
            $pack = $this->createPackWithCode($options['code']);
        }
        else {
            /** @var Arrivage $arrival */
            $arrival = $options['arrival'];

            /** @var Nature $nature */
            $nature = $options['nature'];

            $arrivalNum = $arrival->getNumeroArrivage();
            $newCounter = $arrival->getPacks()->count() + 1;

            if ($newCounter < 10) {
                $newCounter = "00" . $newCounter;
            } elseif ($newCounter < 100) {
                $newCounter = "0" . $newCounter;
            }

            $code = (($nature->getPrefix() ?? '') . $arrivalNum . $newCounter ?? '');
            $pack = $this
                ->createPackWithCode($code)
                ->setNature($nature);

            $arrival->addPack($pack);
        }
        return $pack;
    }

    /**
     * @param string code
     * @return Pack
     */
    public function createPackWithCode(string $code): Pack {
        $pack = new Pack();
        $pack->setCode($code);
        return $pack;
    }

    public function getHighestCodeByPrefix(Arrivage $arrivage): int {
        /** @var Pack $lastColis */
        $lastColis = $arrivage->getPacks()->last();
        $lastCode = $lastColis ? $lastColis->getCode() : null;
        $lastCodeSplitted = isset($lastCode) ? explode('-', $lastCode) : null;
        return (int) ((isset($lastCodeSplitted) && count($lastCodeSplitted) > 1)
            ? $lastCodeSplitted[1]
            : 0);
    }

    /**
     * @param Arrivage $arrivage
     * @param array $colisByNatures
     * @param Utilisateur $user
     * @param EntityManagerInterface $entityManager
     * @return Pack[]
     * @throws Exception
     */
    public function persistMultiPacks(Arrivage $arrivage,
                                      array $colisByNatures,
                                      $user,
                                      EntityManagerInterface $entityManager): array {
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $natureRepository = $this->entityManager->getRepository(Nature::class);
        $defaultEmpForMvt = ($this->specificService->isCurrentClientNameFunction(SpecificService::CLIENT_SAFRAN_ED) && $arrivage->getDestinataire())
            ? $emplacementRepository->findOneByLabel(SpecificService::ARRIVAGE_SPECIFIQUE_SED_MVT_DEPOSE)
            : null;
        if (!isset($defaultEmpForMvt)) {
            $defaultEmpForMvtParam = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MVT_DEPOSE_DESTINATION);
            $defaultEmpForMvt = !empty($defaultEmpForMvtParam)
                ? $emplacementRepository->find($defaultEmpForMvtParam)
                : null;
        }
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $createdPacks = [];
        foreach ($colisByNatures as $natureId => $number) {
            $nature = $natureRepository->find($natureId);
            for ($i = 0; $i < $number; $i++) {
                $pack = $this->createPack(['arrival' => $arrivage, 'nature' => $nature]);
                if ($defaultEmpForMvt) {
                    $mouvementDepose = $this->mouvementTracaService->createTrackingMovement(
                        $pack,
                        $defaultEmpForMvt,
                        $user,
                        $now,
                        false,
                        true,
                        MouvementTraca::TYPE_DEPOSE,
                        ['from' => $arrivage]
                    );
                    $this->mouvementTracaService->persistSubEntities($this->entityManager, $mouvementDepose);
                    $this->entityManager->persist($mouvementDepose);
                }
                $entityManager->persist($pack);
                $createdPacks[] = $pack;
            }
        }
        return $createdPacks;
    }
}