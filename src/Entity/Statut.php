<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\StatutRepository")
 */
class Statut
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
    private $comment;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
    private $displayOrder;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $treated;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategorieStatut", inversedBy="statuts")
     */
    private $categorie;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="statut")
     */
    private $articles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="statut")
     */
    private $receptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="statut")
     */
    private $demandes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Preparation", mappedBy="statut")
     */
    private $preparations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Livraison", mappedBy="statut")
     */
    private $livraisons;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="statut")
     */
    private $collectes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="statut")
     */
    private $referenceArticles;

    /** 
     * @ORM\OneToMany(targetEntity="App\Entity\Manutention", mappedBy="statut")
     */
    private $manutentions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Litige", mappedBy="status")
     */
    private $litiges;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->manutentions = new ArrayCollection();
        $this->litiges = new ArrayCollection();
    }

    public function getId(): ? int
    {
        return $this->id;
    }

    public function getNom(): ? string
    {
        return $this->nom;
    }

    public function setNom(? string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function isTreated(): ?bool {
        return $this->treated;
    }

    public function setTreated(bool $treated): self {
        $this->treated = $treated;
        return $this;
    }

    public function getCategorie(): ? CategorieStatut
    {
        return $this->categorie;
    }

    public function setCategorie(? CategorieStatut $categorie): self
    {
        $this->categorie = $categorie;

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
            $article->setStatut($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getStatut() === $this) {
                $article->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setStatut($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getStatut() === $this) {
                $reception->setStatut(null);
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
            $demande->setStatut($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getStatut() === $this) {
                $demande->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection
    {
        return $this->preparations;
    }

    public function addPreparation(Preparation $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setStatut($this);
        }

        return $this;
    }

    public function removePreparation(Preparation $preparation): self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getStatut() === $this) {
                $preparation->setStatut(null);
            }
        }

        return $this;
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
            $livraison->setStatut($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self
    {
        if ($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if ($livraison->getStatut() === $this) {
                $livraison->setStatut(null);
            }
        }

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
            $collecte->setStatut($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getStatut() === $this) {
                $collecte->setStatut(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->nom;
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
            $referenceArticle->setStatut($this);
        }
        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getStatut() === $this) {
                $referenceArticle->setStatut(null);
            }
        }
        return $this;
    }


    /**
    * @return Collection|Manutention[]
     */
    public function getManutentions(): Collection
    {
        return $this->manutentions;
    }

    public function addManutention(Manutention $manutention): self
    {
        if (!$this->manutentions->contains($manutention)) {
            $this->manutentions[] = $manutention;
            $manutention->setStatut($this);
        }

        return $this;
    }


    public function removeManutention(Manutention $manutention): self
    {
        if ($this->manutentions->contains($manutention)) {
            $this->manutentions->removeElement($manutention);
            // set the owning side to null (unless already changed)
            if ($manutention->getStatut() === $this) {
                $manutention->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Litige[]
     */
    public function getLitiges(): Collection
    {
        return $this->litiges;
    }

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->setStatus($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            // set the owning side to null (unless already changed)
            if ($litige->getStatus() === $this) {
                $litige->setStatus(null);
            }
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getTreated(): ?bool
    {
        return $this->treated;
    }

}
