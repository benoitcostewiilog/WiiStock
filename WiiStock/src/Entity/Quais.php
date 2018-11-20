<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\QuaisRepository")
 */
class Quais
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"entrepots"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"entrepots"})
     */
    private $nom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Entrepots", inversedBy="quais")
     */
    private $entrepots;

    public function getId()
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEntrepots(): ?Entrepots
    {
        return $this->entrepots;
    }

    public function setEntrepots(?Entrepots $entrepots): self
    {
        $this->entrepots = $entrepots;

        return $this;
    }
}
