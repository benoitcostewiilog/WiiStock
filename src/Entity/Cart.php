<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CartRepository::class)
 */
class Cart
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity=utilisateur::class, inversedBy="cart", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToMany(targetEntity=ReferenceArticle::class, inversedBy="carts")
     */
    private $references;

    public function __construct()
    {
        $this->references = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?utilisateur
    {
        return $this->user;
    }

    public function setUser(?utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferences(): Collection
    {
        return $this->references;
    }

    public function addReference(ReferenceArticle $refArticle): self
    {
        if (!$this->references->contains($refArticle)) {
            $this->references[] = $refArticle;
        }

        return $this;
    }

    public function removeReference(ReferenceArticle $refArticle): self
    {
        $this->references->removeElement($refArticle);

        return $this;
    }
}
