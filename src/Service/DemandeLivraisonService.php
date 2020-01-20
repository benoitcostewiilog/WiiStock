<?php


namespace App\Service;


use App\Entity\Demande;
use App\Entity\FiltreSup;
use App\Entity\PrefixeNomDemande;
use App\Entity\Preparation;
use App\Entity\Utilisateur;
use App\Entity\ValeurChampLibre;
use App\Repository\ArticleRepository;
use App\Repository\ChampLibreRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FiltreSupRepository;
use App\Repository\PrefixeNomDemandeRepository;
use App\Repository\ReceptionRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\DemandeRepository;
use Twig\Environment as Twig_Environment;
use App\Repository\StatutRepository;
use App\Repository\TypeRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DemandeLivraisonService
{
    /**
     * @var Twig_Environment
     */
    private $templating;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var FiltreSupRepository
     */
    private $filtreSupRepository;

    /**
     * @var UtilisateurRepository
     */
    private $utilisateurRepository;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var PrefixeNomDemandeRepository
     */
    private $prefixeNomDemandeRepository;

    /**
     * @var ReceptionRepository
     */
    private $receptionRepository;

    /**
     * @var Utilisateur
     */
    private $user;

    private $em;

    public function __construct(TypeRepository $typeRepository,
                                ChampLibreRepository $champLibreRepository,
                                UtilisateurRepository $utilisateurRepository,
                                ReceptionRepository $receptionRepository,
                                PrefixeNomDemandeRepository $prefixeNomDemandeRepository,
                                EmplacementRepository $emplacementRepository,
                                StatutRepository $statutRepository,
                                TokenStorageInterface $tokenStorage,
                                FiltreSupRepository $filtreSupRepository,
                                RouterInterface $router,
                                EntityManagerInterface $em,
                                Twig_Environment $templating,
                                ReferenceArticleRepository $referenceArticleRepository,
                                ArticleRepository $articleRepository,
                                DemandeRepository $demandeRepository)
    {
        $this->utilisateurRepository = $utilisateurRepository;
        $this->typeRepository = $typeRepository;
        $this->champLibreRepository = $champLibreRepository;
        $this->receptionRepository = $receptionRepository;
        $this->prefixeNomDemandeRepository = $prefixeNomDemandeRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->statutRepository = $statutRepository;
        $this->templating = $templating;
        $this->em = $em;
        $this->router = $router;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->filtreSupRepository = $filtreSupRepository;
        $this->user = $tokenStorage->getToken()->getUser();
    }

    public function getDataForDatatable($params = null, $statusFilter = null, $receptionFilter = null)
    {
        if ($statusFilter) {
            $filters = [
                [
                	'field' => 'statut',
					'value' => $statusFilter
				]
            ];
        } else {
            $filters = $this->filtreSupRepository->getFieldAndValueByPageAndUser(FiltreSup::PAGE_DEM_LIVRAISON, $this->user);
        }
        $queryResult = $this->demandeRepository->findByParamsAndFilters($params, $filters, $receptionFilter);

        $demandeArray = $queryResult['data'];

        $rows = [];
        foreach ($demandeArray as $demande) {
            $rows[] = $this->dataRowDemande($demande);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }

    public function dataRowDemande($demande)
    {
        $idDemande = $demande->getId();
        $url = $this->router->generate('demande_show', ['id' => $idDemande]);
        $row =
            [
                'Date' => ($demande->getDate() ? $demande->getDate()->format('d/m/Y') : ''),
                'Demandeur' => ($demande->getUtilisateur()->getUsername() ? $demande->getUtilisateur()->getUsername() : ''),
                'Numéro' => ($demande->getNumero() ? $demande->getNumero() : ''),
                'Statut' => $demande->getStatut() ? $demande->getStatut()->getNom() : '',
                'Type' => ($demande->getType() ? $demande->getType()->getLabel() : ''),
                'Actions' => $this->templating->render('demande/datatableDemandeRow.html.twig',
                    [
                        'idDemande' => $idDemande,
                        'url' => $url,
                    ]
                ),
            ];
        return $row;
    }

    public function newDemande($data) {

        $requiredCreate = true;
        $type = $this->typeRepository->find($data['type']);

        $CLRequired = $this->champLibreRepository->getByTypeAndRequiredCreate($type);
        $msgMissingCL = '';
        foreach ($CLRequired as $CL) {
            if (array_key_exists($CL['id'], $data) and $data[$CL['id']] === "") {
                $requiredCreate = false;
                if (!empty($msgMissingCL)) $msgMissingCL .= ', ';
                $msgMissingCL .= $CL['label'];
            }
        }
        if (!$requiredCreate) {
            return new JsonResponse(['success' => false, 'msg' => 'Veuillez renseigner les champs obligatoires : ' . $msgMissingCL]);
        }
        $utilisateur = $this->utilisateurRepository->find($data['demandeur']);
        $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $statut = $this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_BROUILLON);
        $destination = $this->emplacementRepository->find($data['destination']);
        $type = $this->typeRepository->find($data['type']);

        // génère le numéro
        $prefixeExist = $this->prefixeNomDemandeRepository->findOneByTypeDemande(PrefixeNomDemande::TYPE_LIVRAISON);
        $prefixe = $prefixeExist ? $prefixeExist->getPrefixe() : '';

        $lastNumero = $this->demandeRepository->getLastNumeroByPrefixeAndDate($prefixe, $date->format('ym'));
        $lastCpt = (int)substr($lastNumero, -4, 4);
        $i = $lastCpt + 1;
        $cpt = sprintf('%04u', $i);
        $numero = $prefixe . $date->format('ym') . $cpt;

        $demande = new Demande();
        $demande
            ->setStatut($statut)
            ->setUtilisateur($utilisateur)
            ->setdate($date)
            ->setType($type)
            ->setDestination($destination)
            ->setNumero($numero)
            ->setCommentaire($data['commentaire']);
        $this->em->persist($demande);

        // enregistrement des champs libres
        $champsLibresKey = array_keys($data);

        foreach ($champsLibresKey as $champs) {
            if (gettype($champs) === 'integer') {
                $valeurChampLibre = new ValeurChampLibre();
                $valeurChampLibre
                    ->setValeur($data[$champs])
                    ->addDemandesLivraison($demande)
                    ->setChampLibre($this->champLibreRepository->find($champs));
				$this->em->persist($valeurChampLibre);
				$this->em->flush();
            }
        }
        $this->em->flush();
        // cas où demande directement issue d'une réception
        if (isset($data['reception'])) {
            $demande->setReception($this->receptionRepository->find(intval($data['reception'])));
            $demande->setStatut($this->statutRepository->findOneByCategorieNameAndStatutName(Demande::CATEGORIE, Demande::STATUT_A_TRAITER));
            if (isset($data['needPrepa']) && $data['needPrepa']) {
                $preparation = new Preparation();
                $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                $preparation
                    ->setNumero('P-' . $date->format('YmdHis'))
                    ->setDate($date);
                $statutP = $this->statutRepository->findOneByCategorieNameAndStatutName(Preparation::CATEGORIE, Preparation::STATUT_A_TRAITER);
                $preparation->setStatut($statutP);
                $this->em->persist($preparation);
                $demande->setPreparation($preparation);
            }
			$this->em->flush();
            $data = $demande;
        } else {
            $data = [
                'redirect' => $this->router->generate('demande_show', ['id' => $demande->getId()]),
			];
        }
        return $data;
    }
}
