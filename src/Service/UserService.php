<?php
/**
 * Created by VisualStudioCode.
 * User: jv.Sicot
 * Date: 03/04/2019
 * Time: 15:09
 */

namespace App\Service;

use App\Entity\Action;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Manutention;
use App\Entity\OrdreCollecte;
use App\Entity\Parametre;
use App\Entity\ParametreRole;
use App\Entity\Preparation;
use App\Entity\Reception;
use App\Entity\Role;
use App\Entity\Utilisateur;

use App\Repository\DemandeRepository;
use App\Repository\LivraisonRepository;
use App\Repository\PreparationRepository;
use App\Repository\ManutentionRepository;
use App\Repository\ReceptionRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Twig\Environment as Twig_Environment;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Security;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class UserService
{
     /**
     * @var Twig_Environment
     */
    private $templating;
    /**
     * @var Utilisateur
     */
    private $user;

     /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;


	/**
	 * @var DemandeRepository
	 */
	private $demandeRepository;

	/**
	 * @var LivraisonRepository
	 */
	private $livraisonRepository;

	/**
	 * @var ManutentionRepository
	 */
	private $manutentionRepository;

	/**
	 * @var PreparationRepository
	 */
	private $preparationRepository;

	/**
	 * @var ReceptionRepository
	 */
	private $receptionRepository;
	private $em;

    public function __construct(ReceptionRepository $receptionRepository,
                                DemandeRepository $demandeRepository,
                                LivraisonRepository $livraisonRepository,
                                ManutentionRepository $manutentionRepository,
                                PreparationRepository $preparationRepository,
                                Twig_Environment $templating,
                                EntityManagerInterface $em,
                                UtilisateurRepository $utilisateurRepository,
                                Security $security)
    {
        $this->user = $security->getUser();
        $this->utilisateurRepository = $utilisateurRepository;
        $this->templating = $templating;
        $this->demandeRepository = $demandeRepository;
        $this->livraisonRepository = $livraisonRepository;
        $this->manutentionRepository = $manutentionRepository;
        $this->preparationRepository = $preparationRepository;
        $this->receptionRepository = $receptionRepository;
        $this->em = $em;
    }

    public function getUserRole($user = null)
    {
        if (!$user) $user = $this->user;

        $role = $user ? $user->getRole() : null;

        return $role;
    }

    public function hasRightFunction(string $menuLabel, string $actionLabel, $user = null)
    {
        if (!$user) $user = $this->user;

        $role = $this->getUserRole($user);
		$actions = $role ? $role->getActions() : [];
		$actionRepository = $this->em->getRepository(Action::class);
        $thisAction = $actionRepository->findOneByMenuLabelAndActionLabel($menuLabel, $actionLabel);

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
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
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
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
    public function dataRowUtilisateur($utilisateur)
    {
        $idUser = $utilisateur->getId();
        $roleRepository = $this->em->getRepository(Role::class);
        $roles = $roleRepository->findAll();

		$row = [
			'id' => $utilisateur->getId() ?? '',
			"Nom d'utilisateur" => $utilisateur->getUsername() ?? '',
			'Email' => $utilisateur->getEmail() ?? '',
			'Dropzone' => $utilisateur->getDropzone() ? $utilisateur->getDropzone()->getLabel() : '',
			'Dernière connexion' => $utilisateur->getLastLogin() ? $utilisateur->getLastLogin()->format('d/m/Y') : '',
			'Rôle' => $this->templating->render('utilisateur/role.html.twig', ['utilisateur' => $utilisateur, 'roles' => $roles]),
			'Actions' => $this->templating->render('utilisateur/datatableUtilisateurRow.html.twig', ['idUser' => $idUser]),
		];

		return $row;
    }

	/**
	 * @return bool
	 */
    public function hasParamQuantityByRef()
	{
		$response = false;

        $parametreRoleRepository = $this->em->getRepository(ParametreRole::class);
        $parametreRepository = $this->em->getRepository(Parametre::class);

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
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
	public function isUsedByDemandsOrOrders($user)
	{
	    $collecteRepository = $this->em->getRepository(Collecte::class);
	    $demandeRepository = $this->em->getRepository(Demande::class);
	    $livraisonRepository = $this->em->getRepository(Livraison::class);
	    $ordreCollecteRepository = $this->em->getRepository(OrdreCollecte::class);
	    $manutentionRepository = $this->em->getRepository(Manutention::class);
	    $preparationRepository = $this->em->getRepository(Preparation::class);
	    $receptionRepository = $this->em->getRepository(Reception::class);

		$nbDemandesLivraison = $demandeRepository->countByUser($user);
		$nbDemandesCollecte = $collecteRepository->countByUser($user);
		$nbOrdresLivraison = $livraisonRepository->countByUser($user);
		$nbOrdresCollecte = $ordreCollecteRepository->countByUser($user);
		$nbManutentions = $manutentionRepository->countByUser($user);
		$nbPrepa = $preparationRepository->countByUser($user);
		$nbReceptions = $receptionRepository->countByUser($user);

		return $nbDemandesLivraison + $nbDemandesCollecte + $nbOrdresLivraison + $nbOrdresCollecte + $nbManutentions + $nbPrepa + $nbReceptions > 0;
	}
}
