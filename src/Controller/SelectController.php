<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\CategoryType;
use App\Entity\Emplacement;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\LocationGroup;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

class SelectController extends AbstractController {

    /**
     * @Route("/select/emplacement", name="ajax_select_locations", options={"expose": true})
     */
    public function locations(Request $request, EntityManagerInterface $manager): Response {
        $deliveryType = $request->query->get("deliveryType") ?? null;
        $collectType = $request->query->get("collectType") ?? null;
        $results = $manager->getRepository(Emplacement::class)->getForSelect(
            $request->query->get("term"),
            $deliveryType,
            $collectType
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/services", name="ajax_select_handling_type", options={"expose": true})
     */
    public function handlingType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_HANDLING,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/livraisons", name="ajax_select_delivery_type", options={"expose": true})
     */
    public function deliveryType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_LIVRAISON,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/types/collectes", name="ajax_select_collect_type", options={"expose": true})
     */
    public function collectType(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Type::class)->getForSelect(
            CategoryType::DEMANDE_COLLECTE,
            $request->query->get("term")
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/statuts", name="ajax_select_status", options={"expose": true})
     */
    public function status(Request $request, EntityManagerInterface $manager): Response {
        $type = $request->query->get("type") ?? $request->query->get("handlingType") ?? null;
        $results = $manager->getRepository(Statut::class)->getForSelect(
            $request->query->get("term"),
            $type
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/references", name="ajax_select_references", options={"expose": true})
     */
    public function references(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(ReferenceArticle::class)->getForSelect(
            $request->query->get("term"),
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/capteurs-bruts", name="ajax_select_sensors", options={"expose": true})
     */
    public function sensors(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Sensor::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/capteurs", name="ajax_select_sensor_wrappers", options={"expose"=true})
     */
    public function getSensorWrappers(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/capteurs/sans-action", name="ajax_select_sensor_wrappers_for_pairings", options={"expose"=true})
     */
    public function getSensorWrappersForPairings(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(SensorWrapper::class)
            ->getForSelect($request->query->get("term"), true);

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/colis-sans-association", name="ajax_select_packs_without_pairing", options={"expose"=true})
     */
    public function packsWithoutPairing(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Pack::class)->findWithNoPairing($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/articles-sans-association", name="ajax_select_articles_without_pairing", options={"expose"=true})
     */
    public function articlesWithoutPairing(Request $request, EntityManagerInterface $entityManager): Response {
        $results = $entityManager->getRepository(Article::class)->findWithNoPairing($request->query->get("term"));

        return $this->json([
            "results" => $results
        ]);
    }

    /**
     * @Route("/select/emplacements-sans-association", name="ajax_select_locations_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function locationsWithoutPairing(Request $request, EntityManagerInterface $entityManager){
        $locationGroups = $entityManager->getRepository(LocationGroup::class)->getWithNoAssociationForSelect($request->query->get("term"));
        $locations = $entityManager->getRepository(Emplacement::class)->getWithNoAssociationForSelect($request->query->get("term"));
        $allLocations = array_merge($locations, $locationGroups);
        usort($allLocations, fn($a, $b) => strtolower($a['text']) <=> strtolower($b['text']));

        return $this->json([
            'results' => $allLocations
        ]);
    }

    /**
     * @Route("/select/capteurs-sans-association", name="ajax_select_sensors_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function sensorsWithoutPairings(Request $request, EntityManagerInterface $entityManager){
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"),'name');
        $sensorWrapper = Stream::from($sensorWrapper)
            ->filter(function(SensorWrapper $wrapper) {
                return $wrapper->getPairings()->filter(function(Pairing $pairing) {
                    return $pairing->isActive();
                })->isEmpty();
            })
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getName(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }
    /**
     * @Route("/select/code-capteurs-sans-association", name="ajax_select_sensors_code_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function sensorsWithoutPairingsCode(Request $request, EntityManagerInterface $entityManager){
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'code');
        $sensorWrapper = Stream::from($sensorWrapper)
            ->filter(function(SensorWrapper $wrapper) {
                return $wrapper->getPairings()->filter(function(Pairing $pairing) {
                    return $pairing->isActive();
                })->isEmpty();
            })
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getSensor()->getCode(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }
    /**
     * @Route("/select/actionneur-code-capteurs-sans-association", name="ajax_select_trigger_sensors_code_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function triggerSensorsCodeWithoutPairings(Request $request, EntityManagerInterface $entityManager){
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'code',true);
        $sensorWrapper = Stream::from($sensorWrapper)
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getSensor()->getCode(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

    /**
     * @Route("/select/actionneur-capteurs-sans-association", name="ajax_select_trigger_sensors_without_pairing", options={"expose"=true}, methods="GET|POST")
     */
    public function triggerSensorWithoutPairings(Request $request, EntityManagerInterface $entityManager){
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->getWithNoAssociationForSelect($request->query->get("term"), 'name', true);
        $sensorWrapper = Stream::from($sensorWrapper)
            ->map(fn(SensorWrapper $wrapper) => ['id' => $wrapper->getId(), 'text' => $wrapper->getName(), 'name' => $wrapper->getName(), 'code' => $wrapper->getSensor()->getCode()])
            ->values();
        return $this->json([
            'results' => $sensorWrapper
        ]);
    }

}
