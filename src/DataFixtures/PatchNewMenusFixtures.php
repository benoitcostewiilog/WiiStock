<?php

namespace App\DataFixtures;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class PatchNewMenusFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(EntityManagerInterface $entityManager) {
    }

    public function load(ObjectManager $manager)
    {
    	$formerActionToNewAction = [
			'Réception/lister' => [Menu::ORDRE => [Action::DISPLAY_RECE]],
			'Réception/créer+modifier' => [Menu::ORDRE => [Action::CREATE, Action::EDIT]],
			'Réception/supprimer' => [Menu::ORDRE => [Action::DELETE]],
			'Réception/création réf depuis réception' => [Menu::ORDRE => [Action::CREATE_REF_FROM_RECEP]],
			'Réception/exporter' => [Menu::ORDRE => [Action::EXPORT]],
			'Préparation/lister' => [Menu::ORDRE => [Action::DISPLAY_PREPA]],
			'Préparation/créer+modifier' => [Menu::ORDRE => [Action::CREATE, Action::EDIT]],
			'Préparation/exporter' => [Menu::ORDRE => [Action::EXPORT]],
			'Livraison/lister' => [Menu::ORDRE => [Action::DISPLAY_ORDRE_LIVR]],
			'Livraison/créer+modifier' => [Menu::ORDRE => [Action::CREATE, Action::EDIT]],
			'Livraison/exporter' => [Menu::ORDRE => [Action::EXPORT]],
			'Demande de livraison/lister' => [Menu::DEM => [Action::DISPLAY_DEM_LIVR]],
			'Demande de livraison/créer+modifier' => [Menu::DEM => [Action::CREATE, Action::EDIT]],
			'Demande de livraison/supprimer' => [Menu::DEM => [Action::DELETE]],
			'Demande de livraison/exporter' => [Menu::DEM => [Action::EXPORT]],
			'Demande de collecte/lister' => [Menu::DEM => [Action::DISPLAY_DEM_COLL]],
			'Demande de collecte/créer+modifier' => [Menu::DEM => [Action::CREATE, Action::EDIT]],
			'Demande de collecte/supprimer' => [Menu::DEM => [Action::DELETE]],
            'Demande d\'acheminement/supprimer' =>[Menu::DEM => [Action::DELETE_ACHE]],
            'Demande d\'acheminement/ créer' =>[Menu::DEM => [Action::CREATE_ACHE]],
			'Collecte/lister' => [Menu::ORDRE => [Action::DISPLAY_ORDRE_COLL]],
			'Collecte/créer+modifier' => [Menu::ORDRE => [Action::CREATE, Action::EDIT]],
			'Collecte/exporter' => [Menu::ORDRE => [Action::EXPORT]],
			'Service/lister' => [Menu::DEM => [Action::DISPLAY_HAND]],
			'Service/créer' => [Menu::DEM => [Action::CREATE]],
			'Service/modifier+supprimer' => [Menu::DEM => [Action::EDIT, Action::DELETE]],
			'Service/exporter' => [Menu::DEM => [Action::EXPORT]],
			'Paramétrage/oui' => [Menu::PARAM => [Action::DISPLAY_GLOB, Action::DISPLAY_STATU_LITI, Action::DISPLAY_ROLE, Action::DISPLAY_EXPO, Action::DISPLAY_NATU_COLI, Action::DISPLAY_UTIL, Action::DISPLAY_TYPE, Action::DISPLAY_CF, Action::DISPLAY_INVE, Action::EDIT, Action::DELETE]],
			'Stock/lister' => [Menu::STOCK => [Action::DISPLAY_ARTI, Action::DISPLAY_REFE, Action::DISPLAY_ARTI_FOUR, Action::DISPLAY_MOUV_STOC, Action::DISPLAY_INVE, Action::DISPLAY_ALER]],
			'Stock/créer+modifier' => [Menu::STOCK => [Action::CREATE, Action::EDIT]],
			'Stock/supprimer' => [Menu::STOCK => [Action::DELETE]],
			'Stock/exporter' => [Menu::STOCK => [Action::EXPORT]],
			'Arrivage/lister' => [Menu::TRACA => [Action::DISPLAY_ARRI, Action::DISPLAY_MOUV, Action::DISPLAY_ACHE, Action::DISPLAY_ASSO, Action::DISPLAY_ENCO, Action::DISPLAY_URGE]],
			'Arrivage/créer+modifier' => [Menu::TRACA => [Action::CREATE, Action::EDIT]],
			'Arrivage/supprimer' => [Menu::TRACA => [Action::DELETE]],
			'Arrivage/lister tout' => [Menu::TRACA => [Action::LIST_ALL]],
			'Arrivage/exporter' => [Menu::TRACA => [Action::EXPORT]],
			'Référentiel/lister' => [Menu::REFERENTIEL => [Action::DISPLAY_FOUR, Action::DISPLAY_EMPL, Action::DISPLAY_CHAU, Action::DISPLAY_TRAN]],
			'Référentiel/créer+modifier' => [Menu::REFERENTIEL => [Action::CREATE, Action::EDIT]],
			'Référentiel/supprimer' => [Menu::REFERENTIEL => [Action::DELETE]],
			'Inventaire/lister' => [Menu::STOCK => [Action::DISPLAY_INVE]],
			"Inventaire/gestionnaire d'inventaire" => [Menu::STOCK => [Action::INVENTORY_MANAGER]],
			'Litige/lister' => [Menu::QUALI => [Action::DISPLAY_LITI]],
			'Litige/créer' => [Menu::QUALI => [Action::CREATE]],
			'Litige/modifer' => [Menu::QUALI => [Action::EDIT]],
			'Litige/supprimer' => [Menu::QUALI => [Action::DELETE]],
			'Litige/traiter litige' => [Menu::QUALI => [Action::TREAT_LITIGE]]
		];

        $actionRepository = $manager->getRepository(Action::class);
        $roleRepository = $manager->getRepository(Role::class);
        $roles = $roleRepository->findAll();
		foreach ($formerActionToNewAction as $formerAction => $newMenuAndActions) {
			$formerActionArr = explode('/', $formerAction);
			$formerActionObj = $actionRepository->findOneByMenuLabelAndActionLabel($formerActionArr[0], $formerActionArr[1]);

			foreach ($newMenuAndActions as $newMenu => $newActions) {
				foreach ($roles as $role) {

					if ($role->getActions()->contains($formerActionObj)) {
						foreach ($newActions as $newAction) {
							$newActionObj = $actionRepository->findOneByMenuLabelAndActionLabel($newMenu, $newAction);
							$role->addAction($newActionObj);
						}
						$role->removeAction($formerActionObj);
					}
				}
			}

			if ($formerActionObj) $manager->remove($formerActionObj);
		}

		$manager->flush();

		// suppression des anciens menus
		$query = $manager->createQuery(
		/** @lang DQL */
			"DELETE FROM App\Entity\Menu m
			 WHERE (SELECT count(a) FROM App\Entity\Action a WHERE a.menu = m) = 0");
		$query->execute();
    }

	public function getDependencies()
	{
		return [MenusFixtures::class, ActionsFixtures::class];
	}

    public static function getGroups(): array
    {
        return ['patch-menus'];
    }
}
