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
use Exception;
use Twig\Environment as Twig_Environment;
use Twig\Error\LoaderError as Twig_Error_Loader;
use Twig\Error\RuntimeError as Twig_Error_Runtime;
use Twig\Error\SyntaxError as Twig_Error_Syntax;


/**
 * Class LivraisonsManagerService
 * @package App\Service
 */
class LivraisonsManagerService {

    public const MOUVEMENT_DOES_NOT_EXIST_EXCEPTION = 'mouvement-does-not-exist';
    public const LIVRAISON_ALREADY_BEGAN = 'livraison-already-began';


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
     * @throws Exception
     */
    public function finishLivraison(Utilisateur $user,
                                    Livraison $livraison,
                                    DateTime $dateEnd,
                                    ?Emplacement $emplacementTo): void {
        if (($livraison->getStatut() && $livraison->getStatut()->getNom() === Livraison::STATUT_A_TRAITER) ||
            $livraison->getUtilisateur() && ($livraison->getUtilisateur()->getId() === $user->getId())) {

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
                if ($article->getQuantite() !== 0) {
                    $article
                        ->setStatut($statutRepository->findOneByCategorieNameAndStatutName(CategorieStatut::ARTICLE, Article::STATUT_INACTIF))
                        ->setEmplacement($demande->getDestination());
                }
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
        else {
            throw new Exception(self::LIVRAISON_ALREADY_BEGAN);
        }
    }

}
