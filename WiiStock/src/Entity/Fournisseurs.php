<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FournisseursRepository")
 */
class Fournisseurs
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
    private $code_reference;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Receptions", mappedBy="fournisseur")
     */
    private $receptions;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
    }

    public function getId() : ? int
    {
        return $this->id;
    }

    public function getCodeReference() : ? string
    {
        return $this->code_reference;
    }

    public function setCodeReference(? string $code_reference) : self
    {
        $this->code_reference = $code_reference;

        return $this;
    }

    public function getNom() : ? string
    {
        return $this->nom;
    }

    public function setNom(? string $nom) : self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions() : Collection
    {
        return $this->receptions;
    }

    public function addReceptions(Receptions $reception) : self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReceptions(Receptions $reception) : self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }
}
