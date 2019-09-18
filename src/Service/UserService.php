<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;

use App\Entity\Action;
use App\Entity\Parametre;
use App\Entity\Utilisateur;

use App\Repository\ActionRepository;
use App\Repository\CollecteRepository;
use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\OrdreCollecteRepository;
use App\Repository\ParametreRepository;
use App\Repository\ParametreRoleRepository;
use App\Repository\PreparationRepository;
use App\Repository\ManutentionRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\RoleRepository;

use Symfony\Component\Security\Core\Security;


class UserService
{
     /**
     * @var \Twig_Environment
     */
    private $templating;

    /**
     * @var RoleRepository
     */
    private $roleRepository;
    /**
     * @var Utilisateur
     */
    private $user;

    /**
     * @var ActionRepository
     */
    private $actionRepository;

     /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

	/**
	 * @var ParametreRepository
	 */
	private $parametreRepository;

	/**
	 * @var ParametreRoleRepository
	 */
	private $parametreRoleRepository;

	/**
	 * @var DemandeRepository
	 */
	private $demandeRepository;

	/**
	 * @var LivraisonRepository
	 */
	private $livraisonRepository;

	/**
	 * @var CollecteRepository
	 */
	private $collecteRepository;

	/**
	 * @var OrdreCollecteRepository
	 */
	private $ordreCollecteRepository;

	/**
	 * @var ManutentionRepository
	 */
	private $manutentionRepository;

	/**
	 * @var PreparationRepository
	 */
	private $preparationRepository;

    public function __construct(DemandeRepository $demandeRepository, LivraisonRepository $livraisonRepository, CollecteRepository $collecteRepository, OrdreCollecteRepository $ordreCollecteRepository, ManutentionRepository $manutentionRepository, PreparationRepository $preparationRepository, ParametreRepository $parametreRepository, ParametreRoleRepository $parametreRoleRepository, \Twig_Environment $templating, RoleRepository $roleRepository, UtilisateurRepository $utilisateurRepository, Security $security, ActionRepository $actionRepository)
    {
        $this->user = $security->getUser();
        $this->actionRepository = $actionRepository;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->roleRepository = $roleRepository;
        $this->templating = $templating;
        $this->parametreRepository = $parametreRepository;
        $this->parametreRoleRepository = $parametreRoleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->collecteRepository = $collecteRepository;
        $this->ordreCollecteRepository = $ordreCollecteRepository;
        $this->manutentionRepository = $manutentionRepository;
        $this->preparationRepository = $preparationRepository;
    }

    public function getUserRole($user = null)
    {
        if (!$user) $user = $this->user;

        $role = $user ? $user->getRole() : null;

        return $role;
    }

    public function hasRightFunction(string $menuCode, string $actionLabel = Action::YES, $user = null)
    {
        if (!$user) $user = $this->user;

        $role = $this->getUserRole($user);
		$actions = $role ? $role->getActions() : [];

        $thisAction = $this->actionRepository->findOneByMenuCodeAndLabel($menuCode, $actionLabel);

        if ($thisAction) {
            foreach ($actions as $action) {
                if ($action->getId() == $thisAction->getId()) return true;
            }
        }

        return false;
    }

    public function getDataForDatatable($params = null)
    {
        $data = $this->getUtilisateurDataByParams($params);
        $data['recordsTotal'] = (int)$this->utilisateurRepository->countAll();
        $data['recordsFiltered'] = (int)$this->utilisateurRepository->countAll();
        return $data;
    }

    /**
     * @param null $params
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function getUtilisateurDataByParams($params = null)
    {
        $utilisateurs = $this->utilisateurRepository->findByParams($params);

        $rows = [];
        foreach ($utilisateurs as $utilisateur) {
            $rows[] = $this->dataRowUtilisateur($utilisateur);
        }
        return ['data' => $rows];
    }

    /**
     * @param Utilisateur $utilisateur
     * @return array
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function dataRowUtilisateur($utilisateur)
    {
        $idUser = $utilisateur->getId();
        $roles = $this->roleRepository->findAll();
        
        $row = [
            
                'id' => ($utilisateur->getId() ? $utilisateur->getId() : 'Non défini'),
                "Nom d'utilisateur" => ($utilisateur->getUsername() ? $utilisateur->getUsername() : ''),
                'Email' => ($utilisateur->getEmail() ? $utilisateur->getEmail() : ''),
                'Dernière connexion' => ($utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : ''),
                'Rôle' => $this->templating->render('utilisateur/role.html.twig', ['utilisateur' => $utilisateur, 'roles' => $roles]),
                            'Actions' => $this->templating->render(
                                'utilisateur/datatableUtilisateurRow.html.twig',
                                [
                                    'idUser' => $idUser,
                                ]
                            ),
                        ];
           
        return $row;
    }

	/**
	 * @return bool
	 */
    public function hasParamQuantityByRef()
	{
		$response = false;

		$role = $this->user->getRole();
		$param = $this->parametreRepository->findOneBy(['label' => Parametre::LABEL_AJOUT_QUANTITE]);
		if ($param) {
			$paramQuantite = $this->parametreRoleRepository->findOneByRoleAndParam($role, $param);
			if ($paramQuantite) {
				$response = $paramQuantite->getValue() == Parametre::VALUE_PAR_REF;
			}
		}

		return $response;
	}

	/**
	 * @param Utilisateur|int $user
	 * @return bool
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function isUsedByDemandsOrOrders($user)
	{
		$nbDemandesLivraison = $this->demandeRepository->countByUser($user);
		$nbDemandesCollecte = $this->collecteRepository->countByUser($user);
		$nbOrdresLivraison = $this->livraisonRepository->countByUser($user);
		$nbOrdresCollecte = $this->ordreCollecteRepository->countByUser($user);
		$nbManutentions = $this->manutentionRepository->countByUser($user);
		$nbPrepa = $this->preparationRepository->countByUser($user);

		return $nbDemandesLivraison + $nbDemandesCollecte + $nbOrdresLivraison + $nbOrdresCollecte + $nbManutentions + $nbPrepa > 0;
	}
}
