<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TransporteurRepository")
 */
class Transporteur
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $code;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $nombreChauffeurs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Chauffeur", mappedBy="transporteur")
     */
    private $chauffeurs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="transporteur")
     */
    private $arrivages;

    public function __construct()
    {
        $this->chauffeurs = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getNombreChauffeurs(): ?int
    {
        return $this->nombreChauffeurs;
    }

    public function setNombreChauffeurs(?int $nombreChauffeurs): self
    {
        $this->nombreChauffeurs = $nombreChauffeurs;

        return $this;
    }

    /**
     * @return Collection|Chauffeur[]
     */
    public function getChauffeurs(): Collection
    {
        return $this->chauffeurs;
    }

    public function addChauffeur(Chauffeur $chauffeur): self
    {
        if (!$this->chauffeurs->contains($chauffeur)) {
            $this->chauffeurs[] = $chauffeur;
            $chauffeur->setTransporteur($this);
        }

        return $this;
    }

    public function removeChauffeur(Chauffeur $chauffeur): self
    {
        if ($this->chauffeurs->contains($chauffeur)) {
            $this->chauffeurs->removeElement($chauffeur);
            // set the owning side to null (unless already changed)
            if ($chauffeur->getTransporteur() === $this) {
                $chauffeur->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivages(): Collection
    {
        return $this->arrivages;
    }

    public function addArrivage(Arrivage $arrivage): self
    {
        if (!$this->arrivages->contains($arrivage)) {
            $this->arrivages[] = $arrivage;
            $arrivage->setTransporteur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getTransporteur() === $this) {
                $arrivage->setTransporteur(null);
            }
        }

        return $this;
    }
}
