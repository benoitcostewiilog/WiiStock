<?php


namespace App\Command;

use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\InventoryMission;

use App\Entity\ReferenceArticle;
use App\Repository\StatutRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\InventoryFrequencyRepository;
use App\Repository\InventoryMissionRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;
use function Sodium\add;

class MissionCommand extends Command
{
    protected static $defaultName = 'app:generate:mission';

    /**
     * @var UtilisateurRepository
     */
    private $userRepository;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $referenceArticleRepository;

    /**
     * @var InventoryFrequencyRepository
     */
    private $inventoryFrequencyRepository;

    /**
     * @var InventoryMissionRepository
     */
    private $inventoryMissionRepository;

	/**
	 * @var StatutRepository
	 */
    private $statutRepository;


    public function __construct(StatutRepository $statutRepository, UtilisateurRepository $userRepository, EntityManagerInterface $entityManager, ArticleRepository $articleRepository, ReferenceArticleRepository $referenceArticleRepository, InventoryFrequencyRepository $inventoryFrequencyRepository, InventoryMissionRepository $inventoryMissionRepository)
    {
        parent::__construct();
        $this->userRepository= $userRepository;
        $this->entityManager = $entityManager;
        $this->articleRepository = $articleRepository;
        $this->referenceArticleRepository = $referenceArticleRepository;
        $this->inventoryFrequencyRepository = $inventoryFrequencyRepository;
        $this->inventoryMissionRepository = $inventoryMissionRepository;
        $this->statutRepository = $statutRepository;
    }

    protected function configure()
    {
		$this->setDescription('This commands generates inventory missions.');
        $this->setHelp('This command is supposed to be executed at every end of week, via a cron on the server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new \DateTime('now');
        $frequencies = $this->inventoryFrequencyRepository->findAll();

        $monday = new \DateTime('now');
        $monday->modify('next monday');
        $mission = $this->inventoryMissionRepository->findFirstByStartDate($monday->format('Y/m/d'));

        if (!$mission) {
        	$mission = new InventoryMission();

        	$sunday = new \DateTime('now');
        	$sunday->modify('next monday + 6 days');

        	$mission
				->setStartPrevDate($monday)
				->setEndPrevDate($sunday);
        	$this->entityManager->persist($mission);
        	$this->entityManager->flush();
		}

        foreach ($frequencies as $frequency) {
            $nbMonths = $frequency->getNbMonths();
            $refArticles = $this->referenceArticleRepository->findByFrequencyOrderedByLocation($frequency);

            $refsAndArtToInv = [];
            foreach ($refArticles as $refArticle) {
            	if ($refArticle->getTypeQuantite() == ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
					$refDate = $refArticle->getDateLastInventory();
					if ($refDate) {
						$diff = date_diff($refDate, $now)->format('%m');
						if ($diff >= $nbMonths) {
							$refsAndArtToInv[] = $refArticle;
						}
					}
				} else {
            		$statut = $this->statutRepository->findOneByCategorieAndStatut(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
            		$articles = $this->articleRepository->findByRefArticleAndStatut($refArticle, $statut);

            		foreach ($articles as $article) {
   						$artDate = $article->getDateLastInventory();
   						if ($artDate) {
   							$diff = date_diff($artDate, $now)->format('%m');
   							if ($diff >= $nbMonths) {
   								$refsAndArtToInv[] = $article;
							}
						}
					}
				}
            }

            /** @var ReferenceArticle $refOrArt */
            foreach ($refsAndArtToInv as $refOrArt) {
                $refOrArt->addInventoryMission($mission);
                $this->entityManager->flush();
            }
        }
    }
}