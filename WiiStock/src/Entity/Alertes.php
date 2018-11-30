<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AlertesRepository")
 */
class Alertes
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferencesArticles")
     * @ORM\JoinColumn(nullable=false)
     */
    private $reference;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Alertes")
     * @ORM\JoinColumn(nullable=false)
     */
    private $alerte;

    /**
     * @ORM\Column(type="integer")
     */
    private $seuil;

    public function getId()
    {
        return $this->id;
    }

    public function getReference(): ?ReferencesArticles
    {
        return $this->reference;
    }

    public function setReference(?ReferencesArticles $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getAlerte(): ?Alertes
    {
        return $this->alerte;
    }

    public function setAlerte(?Alertes $alerte): self
    {
        $this->alerte = $alerte;

        return $this;
    }

    public function getSeuil(): ?int
    {
        return $this->seuil;
    }

    public function setSeuil(int $seuil): self
    {
        $this->seuil = $seuil;

        return $this;
    }
}
