<?php


namespace App\EventListener;


use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;

class RefArticleQuantityNotifier
{

    private $refArticleService;
    private $entityManager;

    public function __construct(RefArticleDataService $refArticleDataService,
                                EntityManagerInterface $entityManager)
    {
        $this->refArticleService = $refArticleDataService;
        $this->entityManager = $entityManager;
    }

    public function postUpdate(ReferenceArticle $referenceArticle)
    {
    	$this->refArticleService->treatAlert($referenceArticle);
        $this->entityManager->flush();
    }

    public function postPersist(ReferenceArticle $referenceArticle)
    {
        if ($referenceArticle->getTypeQuantite() === ReferenceArticle::TYPE_QUANTITE_REFERENCE) {
            $this->refArticleService->treatAlert($referenceArticle);
            $this->entityManager->flush();
        }
    }
}
