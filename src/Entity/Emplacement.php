<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity(repositoryClass="App\Repository\EmplacementRepository")
 */
class Emplacement
{

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Livraison", mappedBy="destination")
     */
    private $livraisons;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="destination")
     */
    private $demandes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="pointCollecte")
     */
    private $collectes;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="emplacement")
     */
    private $articles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="emplacement")
     */
    private $referenceArticles;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isDeliveryPoint;

	/**
	 * @ORM\Column(type="boolean", nullable=false, options={"default": true})
	 */
    private $isActive;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private $dateMaxTime;


    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->isActive = true;
    }

    public function getId(): ? int
    {
        return $this->id;
    }

    public function getLabel(): ? string
    {
        return $this->label;
    }

    public function setLabel(? string $label): self {
        $this->label = $label;

        return $this;
    }

    public function __toString()
    {
        return $this->label;
    }

    /**
     * @return Collection|Livraison[]
     */
    public function getLivraisons(): Collection
    {
        return $this->livraisons;
    }

    public function addLivraison(Livraison $livraison): self
    {
        if (!$this->livraisons->contains($livraison)) {
            $this->livraisons[] = $livraison;
            $livraison->setDestination($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self
    {
        if ($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if ($livraison->getDestination() === $this) {
                $livraison->setDestination(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): self
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->setDestination($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getDestination() === $this) {
                $demande->setDestination(null);
            }
        }

        return $this;
    }

    public function getDescription(): ? string
    {
        return $this->description;
    }

    public function setDescription(? string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|Collecte[]
     */
    public function getCollectes(): Collection
    {
        return $this->collectes;
    }

    public function addCollecte(Collecte $collecte): self
    {
        if (!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setPointCollecte($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getPointCollecte() === $this) {
                $collecte->setPointCollecte(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setEmplacement($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getEmplacement() === $this) {
                $article->setEmplacement(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection
    {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setEmplacement($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getEmplacement() === $this) {
                $referenceArticle->setEmplacement(null);
            }
        }

        return $this;
    }

    public function getIsDeliveryPoint(): ?bool
    {
        return $this->isDeliveryPoint;
    }

    public function setIsDeliveryPoint(?bool $isDeliveryPoint): self
    {
        $this->isDeliveryPoint = $isDeliveryPoint;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getDateMaxTime(): ?string
    {
        return $this->dateMaxTime;
    }

    public function setDateMaxTime(?string $dateMaxTime): self
    {
        $this->dateMaxTime = $dateMaxTime;

        return $this;
    }

}
