<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Dispatch;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Handling;
use App\Entity\OrdreCollecte;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\Role;
use App\Entity\Utilisateur;

use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as Twig_Environment;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class UserService
{

    public const MIN_MOBILE_KEY_LENGTH = 14;
    public const MAX_MOBILE_KEY_LENGTH = 24;

     /**
     * @var Twig_Environment
     */
    private $templating;
    /**
     * @var Utilisateur
     */
    private $user;

	private $entityManager;
	private $roleService;

    public function __construct(Twig_Environment $templating,
                                RoleService $roleService,
                                EntityManagerInterface $entityManager,
                                Security $security)
    {
        $this->user = $security->getUser();
        $this->templating = $templating;
        $this->entityManager = $entityManager;
        $this->roleService = $roleService;
    }

    public static function CreateMobileLoginKey(int $length = self::MIN_MOBILE_KEY_LENGTH): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function hasRightFunction(string $menuLabel, string $actionLabel, $user = null) {
        $key = $this->roleService->getPermissionKey($menuLabel, $actionLabel);
        return isset($this->roleService->getPermissions($user ?: $this->user)[$key]);
    }

    public function getDataForDatatable($params = null)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);

        $data = $this->getUtilisateurDataByParams($params);
        $data['recordsTotal'] = (int) $utilisateurRepository->countAll();
        $data['recordsFiltered'] = $data['recordsTotal'];
        return $data;
    }

	/**
	 * @param null $params
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function getUtilisateurDataByParams($params = null)
    {
        $utilisateurRepository = $this->entityManager->getRepository(Utilisateur::class);
        $utilisateurs = $utilisateurRepository->findByParams($params);

        $rows = [];
        foreach ($utilisateurs as $utilisateur) {
            $rows[] = $this->dataRowUtilisateur($utilisateur);
        }
        return ['data' => $rows];
    }

	/**
	 * @param Utilisateur $utilisateur
	 * @return array
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowUtilisateur($utilisateur)
    {
        $idUser = $utilisateur->getId();
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $roles = $roleRepository->findAll();

		$row = [
			'id' => $utilisateur->getId() ?? '',
			"Nom d'utilisateur" => $utilisateur->getUsername() ?? '',
			'Email' => $utilisateur->getEmail() ?? '',
			'Dropzone' => $utilisateur->getDropzone() ? $utilisateur->getDropzone()->getLabel() : '',
			'Dernière connexion' => $utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : '',
            'role' => $utilisateur->getRole() ? $utilisateur->getRole()->getLabel() : '',
			'Actions' => $this->templating->render('utilisateur/datatableUtilisateurRow.html.twig', ['idUser' => $idUser])
		];

		return $row;
    }

	/**
	 * @return bool
	 */
    public function hasParamQuantityByRef()
	{
		$response = false;

        $parametreRoleRepository = $this->entityManager->getRepository(ParametreRole::class);
        $parametreRepository = $this->entityManager->getRepository(Parametre::class);

		$role = $this->user->getRole();
		$param = $parametreRepository->findOneBy(['label' => Parametre::LABEL_AJOUT_QUANTITE]);
		if ($param) {
			$paramQuantite = $parametreRoleRepository->findOneByRoleAndParam($role, $param);
			if ($paramQuantite) {
				$response = $paramQuantite->getValue() == Parametre::VALUE_PAR_REF;
			}
		}

		return $response;
	}

    /**
     * @param Utilisateur|int $user
     * @return bool
     */
	public function isUsedByDemandsOrOrders($user)
	{
	    $collecteRepository = $this->entityManager->getRepository(Collecte::class);
	    $demandeRepository = $this->entityManager->getRepository(Demande::class);
	    $livraisonRepository = $this->entityManager->getRepository(Livraison::class);
	    $ordreCollecteRepository = $this->entityManager->getRepository(OrdreCollecte::class);
	    $handlingRepository = $this->entityManager->getRepository(Handling::class);
	    $preparationRepository = $this->entityManager->getRepository(Preparation::class);
        $receptionRepository = $this->entityManager->getRepository(Reception::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

        $isUsedInRequests = $demandeRepository->countByUser($user) > 0;
        $isUsedInCollects = $collecteRepository->countByUser($user) > 0;
        $isUsedInDeliveryOrders = $livraisonRepository->countByUser($user) > 0;
        $isUsedInCollectOrders = $ordreCollecteRepository->countByUser($user) > 0;
        $isUsedInHandlings = $handlingRepository->countByUser($user) > 0;
        $isUsedInPreparationOrders = $preparationRepository->countByUser($user) > 0;
        $isUsedInReceptions = $receptionRepository->countByUser($user) > 0;
        $isUsedInDispatches = $dispatchRepository->countByUser($user) > 0;
        $isUsedInArrivals = $arrivageRepository->countByUser($user) > 0;

		return (
            $isUsedInRequests
            || $isUsedInCollects
            || $isUsedInDeliveryOrders
            || $isUsedInCollectOrders
            || $isUsedInHandlings
            || $isUsedInPreparationOrders
            || $isUsedInReceptions
            || $isUsedInDispatches
            || $isUsedInArrivals
        );
	}

	public function createUniqueMobileLoginKey(EntityManagerInterface $entityManager): string {
	    $utilisateurRepository = $entityManager->getRepository(Utilisateur::class);
        do {
            $mobileLoginKey = UserService::CreateMobileLoginKey();
            $userWithThisKey = $utilisateurRepository->findBy(['mobileLoginKey' => $mobileLoginKey]);
        }
        while(!empty($userWithThisKey));
        return $mobileLoginKey;
    }

}
