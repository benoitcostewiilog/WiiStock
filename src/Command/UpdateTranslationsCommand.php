<?php


namespace App\Command;


use App\Repository\TranslationRepository;

use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateTranslationsCommand extends Command
{
    private $entityManager;
    private $translationRepository;
    private $translationService;

    public function __construct(EntityManagerInterface $entityManager,
		                		TranslationRepository $translationRepository,
                                TranslationService $translationService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->translationRepository = $translationRepository;
        $this->translationService = $translationService;
    }

    protected function configure()
    {
		$this->setName('app:update:translations');
		$this->setDescription('This commands generate the yaml translations.');
        $this->setHelp('This command is supposed to be executed every night.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->translationService->generateTranslationsFile();
    }
}
