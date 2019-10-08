<?php

namespace App\Service;

use App\Entity\MouvementStock;

use App\Repository\ArticleRepository;
use App\Repository\ReferenceArticleRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use DateTime;

class InventoryService
{
	/**
	 * @var EntityManagerInterface
	 */
	private $em;

	/**
	 * @var ReferenceArticleRepository
	 */
    private $referenceArticleRepository;

	/**
	 * @var ArticleRepository
	 */
    private $articleRepository;

	/**
	 * @var
	 */
    private $user;


    public function __construct(Security $security, EntityManagerInterface $em, ReferenceArticleRepository $referenceArticleRepository, ArticleRepository $articleRepository)
    {
		$this->referenceArticleRepository = $referenceArticleRepository;
		$this->articleRepository = $articleRepository;
		$this->em = $em;
		$this->user = $security->getUser();
    }

	public function doTreatAnomaly($reference, $isRef, $newQuantity, $choice, $comment, $user)
	{
		$em = $this->em;

		if ($isRef) {
			$refOrArt = $this->referenceArticleRepository->findOneByReference($reference);
			$quantity = $refOrArt->getQuantiteStock();
		} else {
			$refOrArt = $this->articleRepository->findOneByReference($reference);
			$quantity = $refOrArt->getQuantite();
		}

		$diff = $newQuantity - $quantity;

		if ($choice == 'confirm' && $diff != 0) {
			$mvt = new MouvementStock();
			$mvt
				->setUser($user)
				->setDate(new \DateTime('now'))
				->setComment($comment)
				->setQuantity(abs($diff));

			if ($isRef) {
				$mvt->setRefArticle($refOrArt);
				//TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
				$refOrArt->setQuantiteStock($newQuantity);
			} else {
				$mvt->setArticle($refOrArt);
				//TODO à supprimer quand la quantité sera calculée directement via les mouvements de stock
				$refOrArt->setQuantite($newQuantity);
			}

			$typeMvt = $diff < 0 ? MouvementStock::TYPE_INVENTAIRE_SORTIE : MouvementStock::TYPE_INVENTAIRE_ENTREE;
			$mvt->setType($typeMvt);

			$em->persist($mvt);
		}

		$refOrArt
			->setHasInventoryAnomaly(false)
			->setDateLastInventory(new DateTime('now'));
		$em->flush();
	}
}
