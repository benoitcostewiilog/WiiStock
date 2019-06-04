<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\DateTime;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArrivageRepository")
 */
class Arrivage
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseur", inversedBy="arrivages")
     */
    private $fournisseur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Litige", mappedBy="arrivage")
     */
    private $litiges;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Chauffeur", inversedBy="arrivages")
     */
    private $chauffeur;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $codeTracageTransporteur;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $numeroBL;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="arrivagesDestinataire")
     */
    private $destinataire;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="arrivages")
     */
    private $statut;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", inversedBy="arrivagesAcheteur")
     */
    private $acheteurs;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $piecesJointes = [];

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $numeroReception;

    /**
     * @ORM\Column(type="integer")
     */
    private $nbUM;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transporteur", inversedBy="arrivages")
     */
    private $transporteur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $numeroArrivage;


    public function __construct()
    {
        $this->acheteurs = new ArrayCollection();
        $this->litiges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getChauffeur(): ?Chauffeur
    {
        return $this->chauffeur;
    }

    public function setChauffeur(?Chauffeur $chauffeur): self
    {
        $this->chauffeur = $chauffeur;

        return $this;
    }

    public function getCodeTracageTransporteur(): ?string
    {
        return $this->codeTracageTransporteur;
    }

    public function setCodeTracageTransporteur(?string $codeTracageTransporteur): self
    {
        $this->codeTracageTransporteur = $codeTracageTransporteur;

        return $this;
    }

    public function getNumeroBL(): ?string
    {
        return $this->numeroBL;
    }

    public function setNumeroBL(?string $numeroBL): self
    {
        $this->numeroBL = $numeroBL;

        return $this;
    }

    public function getDestinataire(): ?Utilisateur
    {
        return $this->destinataire;
    }

    public function setDestinataire(?Utilisateur $destinataire): self
    {
        $this->destinataire = $destinataire;

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
     * @return Collection|Utilisateur[]
     */
    public function getAcheteurs(): Collection
    {
        return $this->acheteurs;
    }

    public function addAcheteur(Utilisateur $acheteur): self
    {
        if (!$this->acheteurs->contains($acheteur)) {
            $this->acheteurs[] = $acheteur;
        }

        return $this;
    }

    public function removeAcheteur(Utilisateur $acheteur): self
    {
        if ($this->acheteurs->contains($acheteur)) {
            $this->acheteurs->removeElement($acheteur);
        }

        return $this;
    }

    public function getPiecesJointes(): ?array
    {
        return $this->piecesJointes;
    }

    public function setPiecesJointes(?array $piecesJointes): self
    {
        $this->piecesJointes = $piecesJointes;

        return $this;
    }

    public function addPiecesJointes($pieceJointe) : self
    {
        $this->piecesJointes[] = $pieceJointe;

        return $this;
    }

    public function getNumeroReception(): ?string
    {
        return $this->numeroReception;
    }

    public function setNumeroReception(?string $numeroReception): self
    {
        $this->numeroReception = $numeroReception;

        return $this;
    }

    public function getNbUM(): ?int
    {
        return $this->nbUM;
    }

    public function setNbUM(int $nbUM): self
    {
        $this->nbUM = $nbUM;

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
            $litige->setArrivage($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            // set the owning side to null (unless already changed)
            if ($litige->getArrivage() === $this) {
                $litige->setArrivage(null);
            }
        }

        return $this;
    }

    public function getTransporteur(): ?Transporteur
    {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self
    {
        $this->transporteur = $transporteur;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getNumeroArrivage(): ?string
    {
        return $this->numeroArrivage;
    }

    public function setNumeroArrivage(string $numeroArrivage): self
    {
        $this->numeroArrivage = $numeroArrivage;

        return $this;
    }
}
