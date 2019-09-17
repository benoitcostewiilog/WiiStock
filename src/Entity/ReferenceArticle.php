<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReferenceArticleRepository")
 */
class ReferenceArticle
{

    const CATEGORIE = 'referenceArticle';
    const STATUT_ACTIF = 'actif';
    const STATUT_INACTIF = 'inactif';

    const TYPE_QUANTITE_REFERENCE = 'reference';
    const TYPE_QUANTITE_ARTICLE = 'article';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $libelle;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $reference;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteDisponible;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\AlerteStock", mappedBy="refArticle")
     */
    private $alertesStock;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteReservee;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantiteStock;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticle", mappedBy="reference")
     */
    private $ligneArticles;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", mappedBy="articleReference")
     */
    private $valeurChampsLibres;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="referenceArticles")
     */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleFournisseur", mappedBy="referenceArticle")
     */
    private $articlesFournisseur;

    /**
     * @ORM\Column(type="string", length=16, nullable=true)
     */
    private $typeQuantite;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="referenceArticles")
     */
    private $statut;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CollecteReference", mappedBy="referenceArticle")
     */
    private $collecteReferences;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="referenceArticle")
     */
    private $receptionReferenceArticles;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="referenceArticles")
     */
    private $emplacement;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="refArticle")
	 */
	private $mouvements;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 */
	private $expiryDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryInv", inversedBy="refArticle")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\EntryInventory", mappedBy="refArticle")
     */
    private $entryInventories;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\HistoryCategory", mappedBy="refArticle")
     */
    private $historyCategories;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\MissionInv", mappedBy="refArticle")
     */
    private $missionInvs;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->alertesStock = new ArrayCollection();
        $this->ligneArticles = new ArrayCollection();
        $this->valeurChampsLibres = new ArrayCollection();
        $this->articlesFournisseur = new ArrayCollection();
        $this->collecteReferences = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->mouvements = new ArrayCollection();
        $this->entryInventories = new ArrayCollection();
        $this->historyCategories = new ArrayCollection();
        $this->missionInvs = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function __toString()
    {
        return $this->reference;
    }

    public function getQuantiteDisponible(): ?int
    {
        return $this->quantiteDisponible;
    }

    public function setQuantiteDisponible(?int $quantiteDisponible): self
    {
        $this->quantiteDisponible = $quantiteDisponible;

        return $this;
    }

    /**
     * @return Collection|AlerteStock[]
     */
    public function getAlertesStock(): Collection
    {
        return $this->alertesStock;
    }

    public function addRefArticleAlerte(AlerteStock $refArticleAlerte): self
    {
        if (!$this->alertesStock->contains($refArticleAlerte)) {
            $this->alertesStock[] = $refArticleAlerte;
            $refArticleAlerte->setRefArticle($this);
        }

        return $this;
    }

    public function removeRefArticleAlerte(AlerteStock $refArticleAlerte): self
    {
        if ($this->alertesStock->contains($refArticleAlerte)) {
            $this->alertesStock->removeElement($refArticleAlerte);
            // set the owning side to null (unless already changed)
            if ($refArticleAlerte->getRefArticle() === $this) {
                $refArticleAlerte->setRefArticle(null);
            }
        }

        return $this;
    }

    public function getQuantiteReservee(): ?int
    {
        return $this->quantiteReservee;
    }

    public function setQuantiteReservee(?int $quantiteReservee): self
    {
        $this->quantiteReservee = $quantiteReservee;

        return $this;
    }

    public function getQuantiteStock(): ?int
    {
        return $this->quantiteStock;
    }

    public function setQuantiteStock(?int $quantiteStock): self
    {
        $this->quantiteStock = $quantiteStock;

        return $this;
    }

    /**
     * @return Collection|LigneArticle[]
     */
    public function getLigneArticles(): Collection
    {
        return $this->ligneArticles;
    }

    public function addLigneArticle(LigneArticle $ligneArticle): self
    {
        if (!$this->ligneArticles->contains($ligneArticle)) {
            $this->ligneArticles[] = $ligneArticle;
            $ligneArticle->setReference($this);
        }

        return $this;
    }

    public function removeLigneArticle(LigneArticle $ligneArticle): self
    {
        if ($this->ligneArticles->contains($ligneArticle)) {
            $this->ligneArticles->removeElement($ligneArticle);
            // set the owning side to null (unless already changed)
            if ($ligneArticle->getReference() === $this) {
                $ligneArticle->setReference(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ValeurChampLibre[]
     */
    public function getValeurChampsLibres(): Collection
    {
        return $this->valeurChampsLibres;
    }

    public function addValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres[] = $valeurChampLibre;
            $valeurChampLibre->addArticleReference($this);
        }

        return $this;
    }

    public function removeValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampLibre);
            $valeurChampLibre->removeArticleReference($this);
        }

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return Collection|ArticleFournisseur[]
     */
    public function getArticlesFournisseur(): Collection
    {
        return $this->articlesFournisseur;
    }

    public function addArticleFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if (!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticleFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if ($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if ($articlesFournisseur->getReferenceArticle() === $this) {
                $articlesFournisseur->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getTypeQuantite(): ?string
    {
        return $this->typeQuantite;
    }

    public function setTypeQuantite(?string $typeQuantite): self
    {
        $this->typeQuantite = $typeQuantite;

        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection|CollecteReference[]
     */
    public function getCollecteReferences(): Collection
    {
        return $this->collecteReferences;
    }

    public function addCollecteReference(CollecteReference $collecteReference): self
    {
        if (!$this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences[] = $collecteReference;
            $collecteReference->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeCollecteReference(CollecteReference $collecteReference): self
    {
        if ($this->collecteReferences->contains($collecteReference)) {
            $this->collecteReferences->removeElement($collecteReference);
            // set the owning side to null (unless already changed)
            if ($collecteReference->getReferenceArticle() === $this) {
                $collecteReference->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection|ReceptionReferenceArticle[]
     */
    public function getReceptionReferenceArticles(): Collection
    {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if (!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setReferenceArticle($this);
        }
        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle  $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getReferenceArticle() ===  $this) {
                $receptionReferenceArticle->setReferenceArticle(null);
            }
            return $this;
        }
    }

    public function addArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if (!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setReferenceArticle($this);
        }

        return $this;
    }

    public function removeArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if ($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if ($articlesFournisseur->getReferenceArticle() === $this) {
                $articlesFournisseur->setReferenceArticle(null);
            }
        }

        return $this;
    }

    public function getEmplacement(): ?Emplacement
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setRefArticle($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getRefArticle() === $this) {
                $mouvement->setRefArticle(null);
            }
        }

        return $this;
    }

    public function addAlertesStock(AlerteStock $alertesStock): self
    {
        if (!$this->alertesStock->contains($alertesStock)) {
            $this->alertesStock[] = $alertesStock;
            $alertesStock->setRefArticle($this);
        }

        return $this;
    }

    public function removeAlertesStock(AlerteStock $alertesStock): self
    {
        if ($this->alertesStock->contains($alertesStock)) {
            $this->alertesStock->removeElement($alertesStock);
            // set the owning side to null (unless already changed)
            if ($alertesStock->getRefArticle() === $this) {
                $alertesStock->setRefArticle(null);
            }
        }

        return $this;
    }

    public function addValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->addArticleReference($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            $valeurChampsLibre->removeArticleReference($this);
        }

        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    /**
     * @return Collection|EntryInventory[]
     */
    public function getEntryInventories(): Collection
    {
        return $this->entryInventories;
    }

    public function addEntryInventory(EntryInventory $entryInventory): self
    {
        if (!$this->entryInventories->contains($entryInventory)) {
            $this->entryInventories[] = $entryInventory;
            $entryInventory->setRefArticle($this);
        }

        return $this;
    }

    public function removeEntryInventory(EntryInventory $entryInventory): self
    {
        if ($this->entryInventories->contains($entryInventory)) {
            $this->entryInventories->removeElement($entryInventory);
            // set the owning side to null (unless already changed)
            if ($entryInventory->getRefArticle() === $this) {
                $entryInventory->setRefArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|HistoryCategory[]
     */
    public function getHistoryCategories(): Collection
    {
        return $this->historyCategories;
    }

    public function addHistoryCategory(HistoryCategory $historyCategory): self
    {
        if (!$this->historyCategories->contains($historyCategory)) {
            $this->historyCategories[] = $historyCategory;
            $historyCategory->setRefArticle($this);
        }

        return $this;
    }

    public function removeHistoryCategory(HistoryCategory $historyCategory): self
    {
        if ($this->historyCategories->contains($historyCategory)) {
            $this->historyCategories->removeElement($historyCategory);
            // set the owning side to null (unless already changed)
            if ($historyCategory->getRefArticle() === $this) {
                $historyCategory->setRefArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|MissionInv[]
     */
    public function getMissionInvs(): Collection
    {
        return $this->missionInvs;
    }

    public function addMissionInv(MissionInv $missionInv): self
    {
        if (!$this->missionInvs->contains($missionInv)) {
            $this->missionInvs[] = $missionInv;
            $missionInv->addRefArticle($this);
        }

        return $this;
    }

    public function removeMissionInv(MissionInv $missionInv): self
    {
        if ($this->missionInvs->contains($missionInv)) {
            $this->missionInvs->removeElement($missionInv);
            $missionInv->removeRefArticle($this);
        }

        return $this;
    }

    public function getCategory(): ?CategoryInv
    {
        return $this->category;
    }

    public function setCategory(?CategoryInv $category): self
    {
        $this->category = $category;

        return $this;
    }

}
