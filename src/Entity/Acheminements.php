<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AcheminementsRepository")
 */
class Acheminements
{
    const CATEGORIE = 'acheminements';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_TRAITE = 'traité';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $packs = [];

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="acheminementsReceive")
     * @ORM\JoinColumn(nullable=false)
     */
    private $receiver;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="acheminementsRequester")
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="acheminements")
     * @ORM\JoinColumn(nullable=false)
     */
    private $statut;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="acheminement")
     */
    private $attachements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="acheminementsFrom")
     */
    private $locationFrom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="acheminementsTo")
     */
    private $locationTo;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PackAcheminement", mappedBy="acheminement", orphanRemoval=true)
     */
    private $packAcheminements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="acheminements")
     */
    private $type;

    public function __construct()
    {
        $this->packAcheminements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getPacks(): ?array
    {
        return $this->packs;
    }

    public function setPacks(?array $packs): self
    {
        $this->packs = $packs;
        return $this;
    }

    public function getReceiver(): ?Utilisateur
    {
        return $this->receiver;
    }

    public function setReceiver(?Utilisateur $receiver): self
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        $this->requester = $requester;

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
     * @return Collection|PieceJointe[]
     */
    public function getAttachements(): Collection
    {
        return $this->attachements;
    }

    public function addAttachement(PieceJointe $attachement): self
    {
        if (!$this->attachements->contains($attachement)) {
            $this->attachements[] = $attachement;
            $attachement->setArrivage($this);
        }

        return $this;
    }

    public function removeAttachement(PieceJointe $attachement): self
    {
        if ($this->attachements->contains($attachement)) {
            $this->attachements->removeElement($attachement);
            // set the owning side to null (unless already changed)
            if ($attachement->getArrivage() === $this) {
                $attachement->setArrivage(null);
            }
        }

        return $this;
    }

    public function getLocationFrom(): ?Emplacement
    {
        return $this->locationFrom;
    }

    public function setLocationFrom(?Emplacement $locationFrom): self
    {
        $this->locationFrom = $locationFrom;

        return $this;
    }

    public function getLocationTo(): ?Emplacement
    {
        return $this->locationTo;
    }

    public function setLocationTo(?Emplacement $locationTo): self
    {
        $this->locationTo = $locationTo;

        return $this;
    }

    /**
     * @return Collection|PackAcheminement[]
     */
    public function getPackAcheminements(): Collection
    {
        return $this->packAcheminements;
    }

    public function addPackAcheminement(PackAcheminement $packAcheminement): self
    {
        if (!$this->packAcheminements->contains($packAcheminement)) {
            $this->packAcheminements[] = $packAcheminement;
            $packAcheminement->setAcheminement($this);
        }

        return $this;
    }

    public function removePackAcheminement(PackAcheminement $packAcheminement): self
    {
        if ($this->packAcheminements->contains($packAcheminement)) {
            $this->packAcheminements->removeElement($packAcheminement);
            // set the owning side to null (unless already changed)
            if ($packAcheminement->getAcheminement() === $this) {
                $packAcheminement->setAcheminement(null);
            }
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
}
