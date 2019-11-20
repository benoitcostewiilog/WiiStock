<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Demande;
use App\Entity\Emplacement;
use App\Entity\Livraison;
use App\Entity\MouvementStock;
use App\Entity\Statut;
use App\Entity\Utilisateur;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Twig_Environment;
use Twig_Error_Loader;
use Twig_Error_Runtime;
use Twig_Error_Syntax;


/**
 * Class LivraisonsManagerService
 * @package App\Service
 */
class LivraisonsManagerService {

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';


    private $entityManager;
    private $mailerService;
    private $templating;

    /**
     * LivraisonsManagerService constructor.
     * @param EntityManagerInterface $entityManager
     * @param MailerService $mailerService
     * @param Twig_Environment $templating
     */
    public function __construct(EntityManagerInterface $entityManager,
                                MailerService $mailerService,
                                Twig_Environment $templating) {
        $this->entityManager = $entityManager;
        $this->mailerService = $mailerService;
        $this->templating = $templating;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): self {
        $this->entityManager = $entityManager;
        return $this;
    }

    /**
     * @param Utilisateur $user
     * @param Livraison $livraison
     * @param DateTime $dateEnd
     * @param Emplacement|null $emplacementTo
     * @throws NonUniqueResultException
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Runtime
     * @throws Twig_Error_Syntax
     */
    public function finishLivraison(Utilisateur $user,
                                    Livraison $livraison,
                                    DateTime $dateEnd,
                                    ?Emplacement $emplacementTo): void {
        // repositories
        $statutRepository = $this->entityManager->getRepository(Statut::class);
        $demandeRepository = $this->entityManager->getRepository(Demande::class);
        $mouvementRepository = $this->entityManager->getRepository(MouvementStock::class);


        $livraison
            ->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ORDRE_LIVRAISON, Livraison::STATUT_LIVRE))
            ->setUtilisateur($user)
            ->setDateFin($dateEnd);

        $demande = $demandeRepository->findOneByLivraison($livraison);

        $statutLivre = $statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::DEM_LIVRAISON, Demande::STATUT_LIVRE);
        $demande->setStatut($statutLivre);

        // quantités gérées à la référence
        $ligneArticles = $demande->getLigneArticle();

        foreach ($ligneArticles as $ligneArticle) {
            $refArticle = $ligneArticle->getReference();
            $refArticle->setQuantiteStock($refArticle->getQuantiteStock() - $ligneArticle->getQuantite());
        }

        // quantités gérées à l'article
        $articles = $demande->getArticles();

        foreach ($articles as $article) {
            $article
                ->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_INACTIF))
                ->setEmplacement($demande->getDestination());
        }

        // on termine les mouvements de livraison
        $mouvements = $mouvementRepository->findByLivraison($livraison);

        foreach ($mouvements as $mouvement) {
            $mouvement->setDate($dateEnd);
            if (isset($emplacementTo)) {
                $mouvement->setEmplacementTo($emplacementTo);
            }
        }

        $this->mailerService->sendMail(
            'FOLLOW GT // Livraison effectuée',
            $this->templating->render('mails/mailLivraisonDone.html.twig', [
                'livraison' => $demande,
                'title' => 'Votre demande a bien été livrée.',
            ]),
            $demande->getUtilisateur()->getEmail()
        );
    }

}
